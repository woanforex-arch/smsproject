<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Users\UserResource;
use App\Jobs\ProcessFlutterwaveWebhook;
use App\Models\Event;
use App\Models\OtpVerification;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\SmsMessage;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PaymentService;
use App\Services\RegistrationService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for the bugs fixed during the audit.
 */
class BugFixesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private TicketType $paid;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-123',
            'services.flutterwave.secret_hash' => 'test-secret-hash-abc123',
            'services.flutterwave.amount_in_minor_units' => false,
        ]);

        $this->event = Event::factory()->create(['status' => EventStatus::Published]);

        $this->paid = TicketType::factory()->for($this->event)->create([
            'name' => 'Standard',
            'price' => 5000,
            'requires_payment' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // OTP
    // ---------------------------------------------------------------------

    public function test_an_otp_is_scoped_to_its_registration_context(): void
    {
        $otp = app(OtpService::class);

        Cache::flush();
        $otp->issue('+254700123456', 'registration', 'REF-AAA');

        Cache::flush();
        $issued = $otp->issue('+254700123456', 'registration', 'REF-BBB');

        $this->assertTrue($issued['ok']);

        // The live code belongs to REF-BBB and must not verify REF-AAA.
        $this->assertFalse(
            $otp->verify('+254700123456', $issued['code'], 'registration', 'REF-AAA')['ok']
        );

        $this->assertTrue(
            $otp->verify('+254700123456', $issued['code'], 'registration', 'REF-BBB')['ok']
        );
    }

    public function test_otp_cooldown_reports_a_positive_wait(): void
    {
        Cache::flush();
        config(['ticketing.otp.resend_cooldown_seconds' => 60]);

        app(OtpService::class)->issue('+254700777777', 'registration', 'REF-1');
        $second = app(OtpService::class)->issue('+254700777777', 'registration', 'REF-2');

        $this->assertFalse($second['ok']);
        $this->assertMatchesRegularExpression('/wait \d+s/', $second['message']);
    }

    // ---------------------------------------------------------------------
    // Payments
    // ---------------------------------------------------------------------

    public function test_a_settled_payment_without_tickets_is_repaired_on_replay(): void
    {
        $registration = $this->verify($this->registerPending());

        // Simulate a crash between marking the payment successful and issuing
        // the tickets.
        Payment::create([
            'registration_id' => $registration->id,
            'tx_ref' => 'PAY-REPAIR-1',
            'amount' => 10000,
            'currency' => 'KES',
            'status' => PaymentStatus::Successful,
        ]);

        $this->assertCount(0, $registration->fresh()->tickets);

        (new ProcessFlutterwaveWebhook([
            'event' => 'charge.completed',
            'data' => [
                'id' => 10543791,
                'tx_ref' => 'PAY-REPAIR-1',
                'flw_ref' => '3538029888',
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'KES',
                'payment_type' => 'mpesa',
            ],
        ]))->handle(app(PaymentService::class));

        $this->assertCount(2, $registration->fresh()->tickets);
        $this->assertSame('paid', $registration->fresh()->payment_status);
    }

    public function test_an_unverified_free_registration_cannot_open_the_pay_page(): void
    {
        $free = TicketType::factory()->for($this->event)->create([
            'name' => 'Free',
            'price' => 0,
            'requires_payment' => true,
        ]);

        $registration = $this->registerPending($free, 1);

        // Must not redirect back to itself (the old infinite-loop bug).
        $this->get('/register/'.$registration->reference.'/pay')->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // SMS
    // ---------------------------------------------------------------------

    public function test_a_stale_sms_status_callback_does_not_overwrite_delivered(): void
    {
        $sms = SmsMessage::create([
            'to_phone' => '+254700000000',
            'body' => 'hello',
            'status' => 'delivered',
            'provider_sid' => 'SM-STALE-1',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->postJson('/webhooks/twilio/status', [
            'MessageSid' => 'SM-STALE-1',
            'MessageStatus' => 'queued',
        ])->assertOk();

        $this->assertSame('delivered', $sms->fresh()->status->value);
    }

    public function test_resending_an_sms_updates_the_same_row(): void
    {
        $sms = SmsMessage::create([
            'to_phone' => '+254700000000',
            'body' => 'hello',
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $result = app(SmsService::class)->resend($sms);

        $this->assertSame($sms->id, $result->id);
        $this->assertSame(2, $result->attempts);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_twilio_callback_requires_the_token_when_configured(): void
    {
        config(['services.twilio.webhook_token' => 'top-secret']);

        $sms = SmsMessage::create([
            'to_phone' => '+254700000000',
            'body' => 'hello',
            'status' => 'sent',
            'provider_sid' => 'SM-TOKEN-1',
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/twilio/status', [
            'MessageSid' => 'SM-TOKEN-1',
            'MessageStatus' => 'delivered',
        ])->assertStatus(403);

        $this->postJson('/webhooks/twilio/status', [
            'MessageSid' => 'SM-TOKEN-1',
            'MessageStatus' => 'delivered',
            'token' => 'top-secret',
        ])->assertOk();

        $this->assertSame('delivered', $sms->fresh()->status->value);
    }

    // ---------------------------------------------------------------------
    // Exports
    // ---------------------------------------------------------------------

    public function test_export_rejects_an_invalid_date_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/exports/registrations?from=not-a-date')
            ->assertRedirect();
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $admin = User::factory()->admin()->create();

        $type = TicketType::factory()->for($this->event)->create([
            'price' => 0,
            'requires_payment' => false,
        ]);

        app(RegistrationService::class)->createPending($this->event, $type, [
            'name' => '=cmd|calc',
            'email' => 'evil@example.com',
            'phone' => '+254700999888',
            'quantity' => 1,
        ]);

        $content = $this->actingAs($admin)
            ->get('/admin/exports/registrations')
            ->streamedContent();

        $this->assertStringContainsString("'=cmd", $content);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_staff_cannot_administer_user_accounts(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::canEdit($staff));
        $this->assertFalse(UserResource::canDelete($staff));
    }

    public function test_admin_can_administer_user_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canEdit($admin));
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
}
