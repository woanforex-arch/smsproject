<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates unique, human-readable ticket and registration references.
 */
class ReferenceGenerator
{
    /**
     * Alphabet without ambiguous characters (0/O, 1/I/L) so references can be
     * read aloud and transcribed without error.
     */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * Create a unique ticket reference, retrying on the (very unlikely) event
     * of a collision with an existing row.
     */
    public function ticketReference(): string
    {
        $prefix = (string) config('ticketing.ticket_reference.prefix', 'EVT');
        $separator = (string) config('ticketing.ticket_reference.separator', '-');
        $length = (int) config('ticketing.ticket_reference.length', 8);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.$separator.$this->randomString($length);

            if (! Ticket::where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fall back to something guaranteed unique.
        return $prefix.$separator.Str::upper(Str::random(12));
    }

    /**
     * Create a unique registration reference.
     */
    public function registrationReference(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'REG-'.$this->randomString(10);

            if (! DB::table('registrations')->where('reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'REG-'.Str::upper(Str::random(14));
    }

    /**
     * Issue `quantity` tickets for a registration, assigning each an attendee
     * name derived from the registrant.
     */
    public function issueTickets(
        Event $event,
        TicketType $ticketType,
        \App\Models\Registration $registration,
        int $quantity
    ): \Illuminate\Support\Collection {
        $tickets = collect();

        for ($i = 0; $i < $quantity; $i++) {
            $tickets->push(Ticket::create([
                'registration_id' => $registration->id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'reference' => $this->ticketReference(),
                'attendee_name' => $registration->name,
                'attendee_email' => $registration->email,
                'attendee_phone' => $registration->phone,
                'status' => 'valid',
            ]));
        }

        return $tickets;
    }

    /**
     * Create a unique payment reference for a registration.
     *
     * Derived from the registration reference so support staff can match a
     * payment to a booking by eye, with a random suffix to stay unique when a
     * guest retries payment.
     */
    public function paymentReference(string $registrationReference): string
    {
        $base = 'PAY-'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $registrationReference));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $base.'-'.$this->randomString(6);

            if (! DB::table('payments')->where('tx_ref', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'PAY-'.strtoupper(Str::random(16));
    }

    private function randomString(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}