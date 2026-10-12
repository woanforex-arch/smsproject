<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\OtpVerification;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\TicketType;
use App\Jobs\ProcessFlutterwaveWebhook;
use App\Models\User;
use App\Services\PaymentFulfilmentService;
use App\Services\PaymentService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_HASH = 'test-secret-hash-abc123';

    private Event $event;

    private TicketType $paid;

    private TicketType $free;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-123',
            'services.flutterwave.secret_hash' => self::SECRET_HASH,
            'services.flutterwave.amount_in_minor_units' => false,
        ]);

        $this->admin = User::factory()->admin()->create();

        $this->event = Event::factory()->create(['status' => EventStatus::Published]);

        $this->paid = TicketType::factory()->for($this->event)->create([
            'name' => 'Standard',
            'price' => 5000,
            'requires_payment' => true,
        ]);

        $this->free = TicketType::factory()->for($this->event)->create([
            'name' => 'Free',
            'price' => 0,
            'requires_payment' => true,
        ]);
    }

    public function test_paid_registration_holds_tickets_until_payment_settles(): void
    {
        $registration = $this->registerPending();

        $this->assertSame('pending', $registration->payment_status);

        $registration = $this->verify($registration);

        // OTP verified, but NOT paid and NO tickets yet.
        $this->assertTrue($registration->isVerified());
        $this->assertSame(RegistrationStatus::Verified, $registration->status);
        $this->assertTrue($registration->requiresPayment());
        $this->assertFalse($registration->isPaid());
        $this->assertCount(0, $registration->tickets);
        $this->assertDatabaseCount('tickets', 0);

        // Paying releases them.
        $this->settle($registration);

        $this->assertTrue($registration->fresh()->isPaid());
        $this->assertCount(2, $registration->fresh()->tickets);
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_free_ticket_type_issues_tickets_immediately_with_no_payment(): void
    {
        $registration = $this->registerPending($this->free, 1);

        $registration = $this->verify($registration);

        $this->assertFalse($registration->requiresPayment());
        $this->assertTrue($registration->isPaid());
        $this->assertCount(1, $registration->tickets);
        $this->assertDatabaseCount('payments', 0);
        $this->assertTrue($registration->isComplete());
    }

    public function test_initiate_creates_a_checkout_link_and_stores_the_tx_ref(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-abc123'],
            ]),
        ]);

        $registration = $this->verify($this->registerPending());

        $payment = app(PaymentService::class)->initiate($registration);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-abc123', $payment->checkout_url);
        $this->assertStringStartsWith('PAY-', $payment->tx_ref);

        Http::assertSent(function ($request) use ($payment) {
            $body = $request->data();

            return $request->url() === 'https://api.flutterwave.com/v3/payments'
                && $body['tx_ref'] === $payment->tx_ref
                && $body['amount'] === 10000      // 2 x 5000, major units
                && $body['currency'] === 'KES'
                && $body['customer']['email'] === 'payer@example.com'
                && $body['customer']['phone_number'] === '+254700000111';
        });
    }

    public function test_amount_is_sent_in_minor_units_when_configured(): void
    {
        config(['services.flutterwave.amount_in_minor_units' => true]);

        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/x'],
            ]),
        ]);

        $registration = $this->verify($this->registerPending());

        app(PaymentService::class)->initiate($registration);

        Http::assertSent(fn ($request) => ($request->data()['amount'] ?? null) === 1000000);
    }

    public function test_verify_rejects_a_transaction_whose_amount_does_not_match(): void
    {
        $registration = $this->verify($this->registerPending());

        Http::fake(function ($request) use ($registration) {
            if (str_contains($request->url(), '/v3/payments')) {
                return Http::response([
                    'status' => 'success',
                    'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-x'],
                ]);
            }

            return Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 10543791,
                    'tx_ref' => $registration->latestPayment?->tx_ref,
                    'status' => 'successful',
                    'amount' => 1,           // should be 10000
                    'currency' => 'KES',
                ],
            ]);
        });

        $payment = app(PaymentService::class)->initiate($registration);

        $result = app(PaymentService::class)->confirmByReference($payment);

        $this->assertFalse($result['ok']);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status, 'a mismatch must be held, not failed');
        $this->assertCount(0, $registration->fresh()->tickets);
    }

    public function test_a_failed_transaction_does_not_issue_tickets(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/x'],
            ]),
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 1,
                    'tx_ref' => 'X',
                    'status' => 'failed',
                    'amount' => 10000,
                    'currency' => 'KES',
                ],
            ]),
        ]);

        $registration = $this->verify($this->registerPending());
        $payment = app(PaymentService::class)->initiate($registration);

        $result = app(PaymentService::class)->confirmByReference($payment);

        $this->assertFalse($result['ok']);
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertCount(0, $registration->fresh()->tickets);
    }

    public function test_webhook_dispatches_a_job_rather_than_blocking_the_response(): void
    {
        Queue::fake();

        $registration = $this->verify($this->registerPending());
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-QUEUE-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        $this->postJson('/webhooks/flutterwave', $this->webhookPayload($payment), [
            'verif-hash' => self::SECRET_HASH,
        ])->assertOk();

        Queue::assertPushed(ProcessFlutterwaveWebhook::class);

        // Nothing settled yet — the work is deferred to a worker.
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertCount(0, $registration->fresh()->tickets);
    }

    public function test_queued_webhook_job_settles_the_payment_and_issues_tickets(): void
    {
        $registration = $this->verify($this->registerPending());
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-JOB-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        $payload = $this->webhookPayload($payment);

        (new ProcessFlutterwaveWebhook($payload))->handle(app(PaymentService::class));

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame('10543791', $payment->fresh()->transaction_id);
        $this->assertSame('mpesa', $payment->fresh()->payment_type);
        $this->assertCount(2, $registration->fresh()->tickets);
        $this->assertSame('paid', $registration->fresh()->payment_status);

        // The raw payload is retained for dispute handling.
        $this->assertNotNull($payment->fresh()->raw_payload);
    }

    public function test_webhook_with_an_invalid_hash_is_rejected(): void
    {
        $registration = $this->verify($this->registerPending());
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-TEST-000',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        $this->postJson('/webhooks/flutterwave', $this->webhookPayload($payment), [
            'verif-hash' => 'wrong-hash',
        ])->assertUnauthorized();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_webhook_without_a_configured_secret_is_rejected(): void
    {
        config(['services.flutterwave.secret_hash' => null]);

        $this->postJson('/webhooks/flutterwave', ['event' => 'charge.completed'], [
            'verif-hash' => 'anything',
        ])->assertUnauthorized();
    }

    public function test_non_charge_events_are_ignored(): void
    {
        $this->postJson('/webhooks/flutterwave', [
            'event' => 'transfer.completed',
            'data' => ['status' => 'SUCCESSFUL'],
        ], ['verif-hash' => self::SECRET_HASH])->assertOk()->assertJson(['ignored' => true]);
    }

    public function test_webhook_is_idempotent(): void
    {
        $registration = $this->verify($this->registerPending());
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-IDEMPOTENT-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        $service = app(PaymentService::class);

        $first = $service->handleWebhook($this->webhookPayload($payment));
        $this->assertTrue($first['ok']);
        $this->assertCount(2, $registration->fresh()->tickets);

        // A duplicate delivery must not issue a second set of tickets.
        $second = $service->handleWebhook($this->webhookPayload($payment));
        $this->assertTrue($second['ok']);
        $this->assertSame('Already settled.', $second['message']);
        $this->assertCount(2, $registration->fresh()->tickets);
    }

    public function test_webhook_for_an_unknown_tx_ref_is_reported_not_credited(): void
    {
        $result = app(PaymentService::class)->handleWebhook([
            'event' => 'charge.completed',
            'data' => ['tx_ref' => 'PAY-DOES-NOT-EXIST', 'status' => 'successful', 'amount' => 100],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['payment']);
    }

    public function test_payment_for_an_unverified_registration_does_not_issue_tickets(): void
    {
        // A payment that arrives before OTP verification must not bypass it.
        $registration = $this->registerPending();

        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-UNVERIFIED-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        app(PaymentService::class)->handleWebhook($this->webhookPayload($payment));

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertCount(0, $registration->fresh()->tickets, 'unverified bookings must not be fulfilled');
    }

    public function test_admin_can_mark_a_payment_paid_manually(): void
    {
        $registration = $this->verify($this->registerPending());

        $payment = Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-MANUAL-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Webhook never arrived.',
        ]);

        app(PaymentFulfilmentService::class)->markPaidManually($payment, $this->admin, 'Cash at the door');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame('manual_override', $payment->provider_status);
        $this->assertStringContainsString('manual', (string) $payment->verified_by);
        $this->assertCount(2, $registration->fresh()->tickets);
    }

    public function test_pay_page_redirects_to_tickets_once_complete(): void
    {
        $registration = $this->verify($this->registerPending());

        $this->settle($registration);

        $this->get('/register/'.$registration->reference.'/pay')
            ->assertRedirect('/tickets/'.$registration->fresh()->tickets->first()->reference);
    }

    public function test_pay_page_renders_the_amount_due(): void
    {
        $registration = $this->verify($this->registerPending());

        $this->get('/register/'.$registration->reference.'/pay')
            ->assertOk()
            ->assertSee('10,000.00')
            ->assertSee('KES')
            ->assertSee($registration->event->title);
    }

    public function test_free_registrations_are_redirected_away_from_the_pay_page(): void
    {
        $registration = $this->verify($this->registerPending($this->free, 1));

        // Nothing to pay, so the guest goes straight to their ticket.
        $this->get('/register/'.$registration->reference.'/pay')
            ->assertRedirect('/tickets/'.$registration->fresh()->tickets->first()->reference);
    }

    public function test_pay_page_is_closed_before_otp_verification(): void
    {
        $registration = $this->registerPending();

        $this->get('/register/'.$registration->reference.'/pay')->assertForbidden();
    }

    public function test_checkout_starts_a_payment_and_redirects_to_flutterwave(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-xyz'],
            ]),
        ]);

        $registration = $this->verify($this->registerPending());

        $this->post('/register/'.$registration->reference.'/pay')
            ->assertRedirect('https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-xyz');

        $this->assertDatabaseHas('payments', ['registration_id' => $registration->id]);
    }

    public function test_gateway_error_is_surfaced_to_the_guest_and_recorded(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'error',
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $registration = $this->verify($this->registerPending());

        $this->post('/register/'.$registration->reference.'/pay')
            ->assertSessionHasErrors('payment');

        // The attempt is kept for auditing, but must not linger as payable.
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'registration_id' => $registration->id,
            'status' => PaymentStatus::Failed->value,
            'failure_reason' => 'Invalid API key',
        ]);

        $this->assertCount(0, $registration->fresh()->tickets);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function registerPending(?TicketType $type = null, int $quantity = 2): Registration
    {
        return app(RegistrationService::class)->createPending(
            $this->event,
            $type ?? $this->paid,
            [
                'name' => 'Payer Example',
                'email' => 'payer@example.com',
                'phone' => '+254700000111',
                'quantity' => $quantity,
            ],
        );
    }

    private function verify(Registration $registration, string $code = '123456'): Registration
    {
        OtpVerification::where('phone', $registration->phone)
            ->whereNull('verified_at')
            ->latest('id')
            ->first()
            ->update(['code_hash' => OtpVerification::hashCode($code)]);

        return app(RegistrationService::class)->verifyOtp($registration, $code);
    }

    private function settle(Registration $registration): void
    {
        // A single closure-based fake is required: successive Http::fake()
        // calls merge, so an earlier stub would keep winning the match.
        Http::fake(function ($request) use ($registration) {
            if (str_contains($request->url(), '/v3/payments')) {
                return Http::response([
                    'status' => 'success',
                    'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-settle'],
                ]);
            }

            return Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 10543791,
                    'tx_ref' => $registration->latestPayment?->tx_ref,
                    'status' => 'successful',
                    'amount' => 10000,
                    'currency' => 'KES',
                    'payment_type' => 'mpesa',
                ],
            ]);
        });

        app(PaymentService::class)->initiate($registration);
        app(PaymentService::class)->confirmByReference($registration->fresh()->latestPayment, 'test');
    }

    private function webhookPayload(Payment $payment): array
    {
        return [
            'event' => 'charge.completed',
            'data' => [
                'id' => 10543791,
                'tx_ref' => $payment->tx_ref,
                'flw_ref' => '3538029888',
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'KES',
                'payment_type' => 'mpesa',
            ],
        ];
    }
}