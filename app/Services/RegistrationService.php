<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\TicketIssued;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the public registration flow.
 *
 * For paid ticket types the flow is:
 *   create pending -> OTP -> verify OTP -> redirect to pay -> payment settles
 *   -> tickets issued -> SMS + email
 *
 * For free ticket types payment is skipped entirely and tickets are issued
 * as soon as the OTP is verified.
 */
class RegistrationService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly OtpService $otp,
        private readonly SmsService $sms,
    ) {}

    /**
     * Create a pending registration and dispatch its OTP.
     *
     * @throws ValidationException
     */
    public function createPending(
        Event $event,
        TicketType $ticketType,
        array $data,
        ?User $user = null
    ): Registration {
        $registration = DB::transaction(function () use ($event, $ticketType, $data, $user) {
            $quantity = (int) $data['quantity'];

            // Lock the ticket type row so two simultaneous requests cannot both
            // pass the availability check and oversell the last remaining seat.
            $locked = TicketType::whereKey($ticketType->id)->lockForUpdate()->firstOrFail();

            $this->guardAvailability($event, $locked, $quantity);

            $registration = Registration::create([
                'event_id' => $event->id,
                'ticket_type_id' => $locked->id,
                'user_id' => $user?->id,
                'reference' => $this->references->registrationReference(),
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'quantity' => $quantity,
                'amount' => round((float) $locked->price * $quantity, 2),
                'currency' => $locked->currency,
                'status' => RegistrationStatus::PendingOtp,
                'payment_status' => $locked->needsPayment() ? 'pending' : 'not_required',
                'ip_address' => request()->ip(),
            ]);

            // Reserve inventory up-front so the hold is released on the
            // registration itself rather than only when tickets are issued.
            $locked->increment('quantity_sold', $quantity);

            return $registration;
        });

        $result = $this->otp->issue(
            phone: $registration->phone,
            purpose: 'registration',
            context: $registration->reference,
            registrationId: $registration->id,
        );

        if (! $result['ok']) {
            // Roll the reservation back if we could not even start the flow.
            $registration->update(['status' => RegistrationStatus::Cancelled]);
            $ticketType->decrement('quantity_sold', (int) $data['quantity']);

            throw ValidationException::withMessages(['phone' => $result['message']]);
        }

        return $registration->refresh();
    }

    /**
     * Verify the OTP.
     *
     * For free bookings this also issues the tickets and sends confirmations.
     * For paid bookings tickets are held back until payment settles — see
     * fulfil().
     *
     * @throws ValidationException
     */
    public function verifyOtp(Registration $registration, string $code): Registration
    {
        if ($registration->isVerified()) {
            return $registration;
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            throw ValidationException::withMessages([
                'code' => 'This registration was cancelled. Please start a new booking.',
            ]);
        }

        $result = $this->otp->verify($registration->phone, $code, 'registration', $registration->reference);

        if (! $result['ok']) {
            $registration->increment('otp_attempts');

            throw ValidationException::withMessages(['code' => $result['message']]);
        }

        $registration->update([
            'status' => RegistrationStatus::Verified,
            'otp_verified_at' => now(),
        ]);

        $registration = $registration->fresh();

        // Tickets are held back until payment settles — unless the payment
        // already arrived before verification (see PaymentFulfilmentService).
        if ($registration->requiresPayment() && ! $registration->isPaid()) {
            return $registration;
        }

        $this->fulfil($registration);

        return $registration->refresh();
    }

    /**
     * Issue tickets for a settled registration and notify the guest.
     *
     * Safe to call more than once: a row lock plus an existence check means a
     * webhook and a redirect landing at the same moment cannot both issue
     * tickets.
     */
    public function fulfil(Registration $registration): void
    {
        if ($registration->tickets()->exists()) {
            return;
        }

        $event = $registration->event;
        $ticketType = $registration->ticketType;

        $issued = DB::transaction(function () use ($registration, $event, $ticketType) {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->first();

            if (! $locked || $locked->tickets()->exists()) {
                return false;
            }

            $this->references->issueTickets(
                event: $event,
                ticketType: $ticketType,
                registration: $registration,
                quantity: (int) $registration->quantity,
            );

            if ($locked->requiresPayment()) {
                $locked->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            return true;
        });

        // A webhook and a redirect landing at the same moment must not BOTH
        // notify the guest; only the caller that actually issued the tickets
        // sends the confirmation.
        if (! $issued) {
            return;
        }

        $this->sendConfirmation($registration);
    }

    /**
     * Re-send the OTP for a pending registration.
     *
     * @throws ValidationException
     */
    public function resendOtp(Registration $registration): void
    {
        if ($registration->isVerified()) {
            throw ValidationException::withMessages([
                'code' => 'This registration is already verified.',
            ]);
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            throw ValidationException::withMessages([
                'code' => 'This registration was cancelled. Please start a new booking.',
            ]);
        }

        $result = $this->otp->issue(
            phone: $registration->phone,
            purpose: 'registration',
            context: $registration->reference,
            registrationId: $registration->id,
        );

        if (! $result['ok']) {
            throw ValidationException::withMessages(['phone' => $result['message']]);
        }
    }

    /**
     * Send the ticket-reference SMS and the e-ticket email.
     */
    public function sendConfirmation(Registration $registration): void
    {
        $event = $registration->event;

        $lines = [
            "Your ticket for {$event->title}",
            'Ref: '.$registration->reference,
            '',
        ];

        foreach ($registration->tickets as $ticket) {
            $lines[] = $ticket->reference.' — '.$ticket->ticketType->name;
        }

        $lines[] = '';
        $lines[] = $event->starts_at->format('D, j M Y \a\t g:i a');
        $lines[] = $event->venue;

        $this->sms->send(
            toPhone: $registration->phone,
            body: implode("\n", $lines),
            type: 'ticket_reference',
            registrationId: $registration->id,
        );

        $registration->notify(new TicketIssued($registration));
    }

    /**
     * Re-send the ticket reference SMS without re-sending the email.
     */
    public function resendTicketSms(Registration $registration): void
    {
        $event = $registration->event;

        $body = "Your ticket for {$event->title}\nRef: {$registration->reference}\n"
            .$registration->tickets->pluck('reference')->implode(', ');

        $this->sms->send(
            toPhone: $registration->phone,
            body: $body,
            type: 'ticket_reference',
            registrationId: $registration->id,
        );
    }

    /**
     * Ensure the event/ticket type combination can still accept the request.
     *
     * @throws ValidationException
     */
    private function guardAvailability(Event $event, TicketType $ticketType, int $quantity): void
    {
        if (! $event->isOpenForRegistration()) {
            throw ValidationException::withMessages([
                'event' => 'Registration for this event is closed.',
            ]);
        }

        if (! $ticketType->isOnSale()) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'This ticket type is not currently on sale.',
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Please select at least one ticket.',
            ]);
        }

        if ($quantity > $ticketType->quantity_per_order_max) {
            throw ValidationException::withMessages([
                'quantity' => "You can purchase at most {$ticketType->quantity_per_order_max} tickets per order.",
            ]);
        }

        if ($ticketType->quantityRemaining() < $quantity) {
            throw ValidationException::withMessages([
                'ticket_type_id' => "Only {$ticketType->quantityRemaining()} ticket(s) remaining.",
            ]);
        }
    }
}