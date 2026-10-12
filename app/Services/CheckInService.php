<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles ticket check-in for both manual reference entry and QR scanning.
 */
class CheckInService
{
    /**
     * Check in a ticket by its exact reference.
     *
     * @return array{ok: bool, ticket?: Ticket, message?: string, code?: string}
     */
    public function checkInByReference(
        string $reference,
        User $user,
        string $method = 'manual',
        ?string $ip = null,
        ?string $notes = null
    ): array {
        $reference = trim($reference);

        if ($reference === '') {
            return ['ok' => false, 'code' => 'empty', 'message' => 'Enter or scan a ticket reference.'];
        }

        $ticket = Ticket::with(['event', 'ticketType'])
            ->whereRaw('LOWER(reference) = ?', [mb_strtolower($reference)])
            ->first();

        if (! $ticket) {
            return ['ok' => false, 'code' => 'not_found', 'message' => "Ticket not found: {$reference}"];
        }

        return $this->checkInTicket($ticket, $user, $method, $ip, $notes);
    }

    /**
     * Check in a ticket decoded from a scanned QR payload.
     *
     * @return array{ok: bool, ticket?: Ticket, message?: string, code?: string}
     */
    public function checkInByQrPayload(
        string $payload,
        User $user,
        ?string $ip = null
    ): array {
        $payload = trim($payload);

        if (! Ticket::isValidPayload($payload)) {
            return ['ok' => false, 'code' => 'invalid_qr', 'message' => 'This QR code is not a valid ticket.'];
        }

        $reference = Ticket::referenceFromPayload($payload);

        return $this->checkInByReference($reference, $user, 'qr', $ip);
    }

    /**
     * Validate a scanned QR payload without performing the check-in. Lets the
     * scanner screen give instant feedback on an unknown/forged code.
     */
    public function lookupByPayload(string $payload): ?Ticket
    {
        if (! Ticket::isValidPayload($payload)) {
            return null;
        }

        return Ticket::with(['event', 'ticketType'])
            ->where('reference', Ticket::referenceFromPayload($payload))
            ->first();
    }

    /**
     * Apply the check-in to a located ticket, guarding against duplicates.
     *
     * @return array{ok: bool, ticket?: Ticket, message?: string, code?: string}
     */
    private function checkInTicket(
        Ticket $ticket,
        User $user,
        string $method,
        ?string $ip,
        ?string $notes
    ): array {
        // Lock the row so two scanners cannot both succeed concurrently.
        return DB::transaction(function () use ($ticket, $user, $method, $ip, $notes) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked) {
                return ['ok' => false, 'code' => 'not_found', 'message' => 'Ticket not found.'];
            }

            if ($locked->status === TicketStatus::CheckedIn) {
                return [
                    'ok' => false,
                    'code' => 'already_checked_in',
                    'ticket' => $locked,
                    'message' => 'This ticket was already checked in at '.$locked->checked_in_at?->format('j M Y, g:i a'),
                ];
            }

            if ($locked->status === TicketStatus::Cancelled) {
                return [
                    'ok' => false,
                    'code' => 'cancelled',
                    'ticket' => $locked,
                    'message' => 'This ticket has been cancelled.',
                ];
            }

            $locked->update([
                'status' => TicketStatus::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by' => $user->id,
                'check_in_method' => $method,
            ]);

            CheckIn::create([
                'ticket_id' => $locked->id,
                'user_id' => $user->id,
                'method' => $method,
                'ip_address' => $ip,
                'notes' => $notes,
            ]);

            Log::info('Ticket checked in', [
                'reference' => $locked->reference,
                'method' => $method,
                'user_id' => $user->id,
            ]);

            return [
                'ok' => true,
                'ticket' => $locked->fresh(['event', 'ticketType', 'checker']),
                'message' => 'Checked in successfully.',
            ];
        });
    }

    /**
     * Reverse a check-in (undo), used when a guest is turned away or the
     * scanner made a mistake.
     */
    public function undo(Ticket $ticket, User $user): array
    {
        return DB::transaction(function () use ($ticket, $user) {
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== TicketStatus::CheckedIn) {
                return ['ok' => false, 'message' => 'This ticket is not currently checked in.'];
            }

            $locked->update([
                'status' => TicketStatus::Valid,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'check_in_method' => null,
            ]);

            CheckIn::where('ticket_id', $locked->id)
                ->latest('id')
                ->first()
                ?->update(['notes' => 'Undo by user #'.$user->id]);

            Log::info('Check-in undone', ['reference' => $locked->reference, 'user_id' => $user->id]);

            return ['ok' => true, 'ticket' => $locked->fresh(['event', 'ticketType']), 'message' => 'Check-in reversed.'];
        });
    }
}