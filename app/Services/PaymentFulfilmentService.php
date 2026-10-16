<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Applies a settled payment to its registration: mark paid, issue tickets,
 * notify the guest.
 *
 * Extracted from the webhook/redirect paths so both, and the admin override,
 * go through exactly the same fulfilment logic.
 */
class PaymentFulfilmentService
{
    public function __construct(
        private readonly RegistrationService $registrations,
    ) {}

    /**
     * Mark the registration paid and issue its tickets.
     *
     * @return bool True when tickets are now issued.
     */
    public function handle(Payment $payment, string $verifiedBy = 'gateway'): bool
    {
        $registration = $payment->registration;

        if (! $registration) {
            Log::error('Payment has no registration', ['payment_id' => $payment->id]);

            return false;
        }

        // Only a verified registration may have tickets issued — otherwise a
        // payment that races ahead of OTP verification would issue tickets
        // silently. Record that the money arrived so verification can release
        // the tickets later.
        if (! $registration->isVerified()) {
            Log::warning('Payment settled for an unverified registration', [
                'registration' => $registration->reference,
            ]);

            $registration->update([
                'payment_status' => 'paid',
                'paid_at' => $payment->paid_at ?? now(),
            ]);

            return false;
        }

        $registration->update([
            'payment_status' => 'paid',
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        $this->registrations->fulfil($registration->fresh());

        return true;
    }

    /**
     * Manually mark a payment as settled (cash, bank transfer, goodwill).
     *
     * Used by the admin panel when a webhook never arrived.
     */
    public function markPaidManually(Payment $payment, User $actor, ?string $note = null): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Successful,
            'provider_status' => 'manual_override',
            'paid_at' => $payment->paid_at ?? now(),
            'verified_at' => now(),
            'verified_by' => 'manual:#'.$actor->id,
            'failure_reason' => $note ? 'Manual: '.$note : null,
        ]);

        Log::info('Payment manually marked as paid', [
            'payment_id' => $payment->id,
            'tx_ref' => $payment->tx_ref,
            'actor' => $actor->id,
        ]);

        $this->handle($payment->refresh(), 'manual:#'.$actor->id);

        return $payment->refresh();
    }
}