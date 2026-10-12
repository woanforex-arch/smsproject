<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\OtpVerification;
use App\Models\SmsMessage;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const KNOWN_OTP = '123456';

    private Event $event;

    private TicketType $ticketType;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->event = Event::create([
            'title' => 'Test Event',
            'slug' => 'test-event',
            'venue' => 'Test Hall',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(5),
            'status' => EventStatus::Published,
            'capacity' => 100,
        ]);

        $this->ticketType = TicketType::create([
            'event_id' => $this->event->id,
            'name' => 'Standard',
            'price' => 5000,
            'currency' => 'KES',
            'quantity_total' => 50,
            'quantity_sold' => 0,
            'quantity_per_order_max' => 5,
            // Payment is covered separately in PaymentFlowTest; here we assert
            // the free path, where tickets are released on OTP verification.
            'requires_payment' => false,
            'is_active' => true,
        ]);
    }

    public function test_a_guest_can_complete_registration_and_receives_tickets(): void
    {
        $service = app(RegistrationService::class);

        $registration = $service->createPending($this->event, $this->ticketType, [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+254700111222',
            'quantity' => 2,
        ]);

        $this->assertSame(RegistrationStatus::PendingOtp, $registration->status);
        $this->assertSame(2, $this->ticketType->fresh()->quantity_sold);
        $this->assertSame(10000.0, (float) $registration->amount);

        // An OTP row exists and an SMS audit record was written for it.
        $this->assertDatabaseHas('otp_verifications', ['phone' => '+254700111222']);
        $this->assertDatabaseHas('sms_messages', [
            'registration_id' => $registration->id,
            'type' => 'otp',
        ]);

        $registration = $service->verifyOtp($registration, $this->forceKnownOtp($registration->phone));

        $this->assertTrue($registration->isVerified());
        $this->assertCount(2, $registration->tickets);
        $this->assertDatabaseHas('tickets', [
            'registration_id' => $registration->id,
            'attendee_name' => 'Jane Doe',
            'status' => TicketStatus::Valid->value,
        ]);
    }

    public function test_ticket_references_are_unique_and_prefixed(): void
    {
        $service = app(RegistrationService::class);

        $registration = $service->createPending($this->event, $this->ticketType, [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'phone' => '+254700333444',
            'quantity' => 3,
        ]);

        $registration = $service->verifyOtp($registration, $this->forceKnownOtp($registration->phone));

        $references = $registration->tickets->pluck('reference');

        $this->assertCount(3, $references);
        $this->assertCount(3, $references->unique());
        $references->each(fn ($r) => $this->assertStringStartsWith('EVT-', $r));
    }

    public function test_sms_delivery_status_callback_updates_the_audit_row(): void
    {
        $sms = SmsMessage::create([
            'to_phone' => '+254700000000',
            'body' => 'hello',
            'status' => 'sent',
            'provider_sid' => 'SM123456789',
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/twilio/status', [
            'MessageSid' => 'SM123456789',
            'MessageStatus' => 'delivered',
        ])->assertOk();

        $this->assertSame('delivered', $sms->fresh()->status->value);
        $this->assertNotNull($sms->fresh()->delivered_at);
    }

    public function test_status_callback_for_an_unknown_sid_is_ignored(): void
    {
        $this->postJson('/webhooks/twilio/status', [
            'MessageSid' => 'SM-does-not-exist',
            'MessageStatus' => 'delivered',
        ])->assertOk();
    }

    public function test_check_in_by_reference_then_rejects_a_second_scan(): void
    {
        $reference = $this->registerAndVerify('Carol', 'carol@example.com', '+254700555666', 1);

        $checkIn = app(CheckInService::class);

        $first = $checkIn->checkInByReference($reference, $this->admin, 'manual');
        $this->assertTrue($first['ok']);
        $this->assertSame(TicketStatus::CheckedIn, $first['ticket']->status);

        $second = $checkIn->checkInByReference($reference, $this->admin, 'manual');
        $this->assertFalse($second['ok']);
        $this->assertSame('already_checked_in', $second['code']);

        $this->assertDatabaseHas('check_ins', ['ticket_id' => $first['ticket']->id, 'method' => 'manual']);
    }

    public function test_check_in_is_case_insensitive_on_the_reference(): void
    {
        $reference = $this->registerAndVerify('Casey', 'casey@example.com', '+254700555777', 1);

        $result = app(CheckInService::class)
            ->checkInByReference(strtolower($reference), $this->admin, 'manual');

        $this->assertTrue($result['ok']);
    }

    public function test_unknown_reference_is_reported_as_not_found(): void
    {
        $result = app(CheckInService::class)->checkInByReference('EVT-NOPE123', $this->admin);

        $this->assertFalse($result['ok']);
        $this->assertSame('not_found', $result['code']);
    }

    public function test_check_in_can_be_undone(): void
    {
        $reference = $this->registerAndVerify('Dana', 'dana@example.com', '+254700555888', 1);

        $service = app(CheckInService::class);
        $first = $service->checkInByReference($reference, $this->admin, 'manual');
        $this->assertTrue($first['ok']);

        $undo = $service->undo($first['ticket'], $this->admin);

        $this->assertTrue($undo['ok']);
        $this->assertSame(TicketStatus::Valid, $undo['ticket']->status);
        $this->assertNull($undo['ticket']->checked_in_at);
    }

    public function test_qr_payload_round_trips_and_rejects_tampering(): void
    {
        $reference = $this->registerAndVerify('Dave', 'dave@example.com', '+254700777888', 1);
        $ticket = Ticket::where('reference', $reference)->firstOrFail();

        $payload = $ticket->qrPayload();
        $this->assertTrue(Ticket::isValidPayload($payload));
        $this->assertSame($ticket->reference, Ticket::referenceFromPayload($payload));

        // A forged signature must be rejected before any database lookup.
        $this->assertFalse(Ticket::isValidPayload($ticket->reference.'.0000000000000000'));
        $this->assertFalse(Ticket::isValidPayload('nonsense'));

        $result = app(CheckInService::class)->checkInByQrPayload($payload, $this->admin);
        $this->assertTrue($result['ok']);

        $forged = app(CheckInService::class)
            ->checkInByQrPayload($ticket->reference.'.0000000000000000', $this->admin);
        $this->assertFalse($forged['ok']);
        $this->assertSame('invalid_qr', $forged['code']);
    }

    public function test_registration_is_rejected_when_ticket_type_is_sold_out(): void
    {
        $this->ticketType->update(['quantity_sold' => 50]);

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->createPending($this->event, $this->ticketType, [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'phone' => '+254700999000',
            'quantity' => 1,
        ]);
    }

    public function test_registration_is_rejected_for_a_closed_event(): void
    {
        $this->event->update(['registration_deadline' => now()->subDay()]);

        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->createPending($this->event, $this->ticketType, [
            'name' => 'Frank',
            'email' => 'frank@example.com',
            'phone' => '+254700999111',
            'quantity' => 1,
        ]);
    }

    public function test_registration_is_rejected_beyond_the_per_order_maximum(): void
    {
        $this->expectException(ValidationException::class);

        app(RegistrationService::class)->createPending($this->event, $this->ticketType, [
            'name' => 'Gail',
            'email' => 'gail@example.com',
            'phone' => '+254700999222',
            'quantity' => 99,
        ]);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $service = app(RegistrationService::class);

        $registration = $service->createPending($this->event, $this->ticketType, [
            'name' => 'Grace',
            'email' => 'grace@example.com',
            'phone' => '+254700999333',
            'quantity' => 1,
        ]);

        $this->forceKnownOtp($registration->phone, '999999');

        $this->expectException(ValidationException::class);

        $service->verifyOtp($registration, '123456');
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Test Event');
        $this->get('/events/test-event')->assertOk();
        $this->get('/events/test-event/register')->assertOk();
        $this->get('/find-ticket')->assertOk();
    }

    public function test_ticket_page_is_reachable_by_reference(): void
    {
        $reference = $this->registerAndVerify('Hank', 'hank@example.com', '+254700999444', 1);

        $this->get("/tickets/{$reference}")->assertOk()->assertSee($reference);
        $this->get("/tickets/{$reference}/print")->assertOk();
    }

    public function test_csv_export_is_restricted_to_admins(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/exports/registrations')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/exports/registrations')->assertOk();
        $this->actingAs($this->admin)->get('/admin/exports/tickets')->assertOk();
        $this->actingAs($this->admin)->get('/admin/exports/summary')->assertOk();
    }

    /**
     * Run a full registration and return the first ticket reference.
     */
    private function registerAndVerify(string $name, string $email, string $phone, int $quantity): string
    {
        $service = app(RegistrationService::class);

        $registration = $service->createPending($this->event, $this->ticketType, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'quantity' => $quantity,
        ]);

        $registration = $service->verifyOtp($registration, $this->forceKnownOtp($phone));

        return $registration->tickets->first()->reference;
    }

    /**
     * Replace the stored OTP hash with one for a known code so the real
     * verification path can be exercised. Returns the code.
     */
    private function forceKnownOtp(string $phone, string $code = self::KNOWN_OTP): string
    {
        OtpVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->latest('id')
            ->first()
            ->update(['code_hash' => OtpVerification::hashCode($code)]);

        return $code;
    }
}