<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates payment: create link -> verify with the gateway -> fulfil.
 *
 * Fulfilment (ticket issuance + notifications) is delegated to the
 * RegistrationService via the fulfiller callback so that the ticket/SMS/email
 * side-effects live in exactly one place.
 */
class PaymentService
{
    public function __construct(
        private readonly FlutterwaveService $gateway,
        private readonly ReferenceGenerator $references,
        private readonly PaymentFulfilmentService $fulfilment,
    ) {}

    /**
     * Start (or resume) a payment for a registration.
     *
     * Reuses the most recent pending payment so a guest who abandons checkout
     * and comes back does not accumulate orphaned rows.
     *
     * @throws PaymentGatewayException
     */
    public function initiate(Registration $registration): Payment
    {
        if (! $this->gateway->isConfigured()) {
            throw PaymentGatewayException::misconfigured();
        }

        $existing = $this->pendingPayment($registration);

        if ($existing && $existing->checkout_url) {
            return $existing;
        }

        $payment = $existing ?? Payment::create([
            'registration_id' => $registration->id,
            'provider' => 'flutterwave',
            'tx_ref' => $this->references->paymentReference($registration->reference),
            'amount' => $registration->amount,
            'currency' => $registration->currency,
            'status' => PaymentStatus::Pending,
        ]);

        $link = $this->createLinkOrFail($registration, $payment);

        $payment->update([
            'checkout_url' => $link,
            'attempts' => $payment->attempts + 1,
            'status' => PaymentStatus::Pending,
        ]);

        return $payment->refresh();
    }

    /**
     * Generate the checkout link, recording the failure on the payment row so
     * the attempt stays auditable instead of lingering as an unpayable row.
     *
     * @throws PaymentGatewayException
     */
    private function createLinkOrFail(Registration $registration, Payment $payment): string
    {
        try {
            return $this->gateway->createPaymentLink($this->buildPayload($registration, $payment));
        } catch (PaymentGatewayException $e) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $e->getMessage(),
                'verified_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Confirm a payment by asking Flutterwave about our stored tx_ref.
     *
     * The tx_ref always comes from the database — never from the request — so
     * a forged redirect or webhook cannot point us at someone else's payment.
     *
     * @return array{ok: bool, payment: Payment, message: string}
     */
    public function confirmByReference(Payment $payment, ?string $verifiedBy = null): array
    {
        if ($payment->isPaid()) {
            // Repair, don't just short-circuit: a crash between marking the
            // payment successful and issuing tickets would otherwise leave a
            // paid booking ticket-less forever. handle() is idempotent.
            $this->fulfilment->handle($payment, $verifiedBy ?? 'gateway');

            return ['ok' => true, 'payment' => $payment, 'message' => 'Already paid.'];
        }

        try {
            $transaction = $this->gateway->verifyByReference($payment->tx_ref);
        } catch (PaymentGatewayException $e) {
            // A transport/HTTP error is not proof the payment failed. Hold it
            // as pending so ReconcilePayments retries instead of blacklisting
            // a payment that may have actually succeeded.
            return $this->fail($payment, $e->getMessage(), hold: true);
        }

        return $this->settle($payment, $transaction, $verifiedBy);
    }

    /**
     * Apply a Flutterwave webhook payload to its payment.
     *
     * @return array{ok: bool, payment: ?Payment, message: string}
     */
    public function handleWebhook(array $payload, ?string $verifiedBy = null): array
    {
        $txRef = $payload['data']['tx_ref'] ?? null;

        if (! is_string($txRef) || $txRef === '') {
            return ['ok' => false, 'payment' => null, 'message' => 'Payload contained no tx_ref.'];
        }

        $payment = Payment::where('tx_ref', $txRef)->first();

        if (! $payment) {
            Log::warning('Flutterwave webhook for an unknown tx_ref', ['tx_ref' => $txRef]);

            return ['ok' => false, 'payment' => null, 'message' => 'Unknown tx_ref.'];
        }

        // Idempotency: Flutterwave retries, and charge.completed fires for
        // success and failure alike.
        if ($payment->isPaid()) {
            // Re-assert fulfilment in case a prior attempt marked the payment
            // successful but crashed before issuing tickets.
            $this->fulfilment->handle($payment, $verifiedBy ?? 'gateway');

            return ['ok' => true, 'payment' => $payment, 'message' => 'Already settled.'];
        }

        $result = $this->settle($payment, $payload['data'] ?? [], $verifiedBy, $payload['event'] ?? null);

        $payment->updateQuietly(['raw_payload' => $payload]);

        return $result;
    }

    /**
     * Compare the gateway transaction against what we asked for, then settle.
     *
     * @return array{ok: bool, payment: Payment, message: string}
     */
    private function settle(Payment $payment, array $transaction, ?string $verifiedBy, ?string $event = null): array
    {
        $providerStatus = (string) ($transaction['status'] ?? '');

        if ($providerStatus !== 'successful') {
            return $this->fail($payment, "Payment {$providerStatus}.");
        }

        $mismatch = $this->detectMismatch($payment, $transaction);

        if ($mismatch !== null) {
            Log::error('Flutterwave transaction mismatch', [
                'tx_ref' => $payment->tx_ref,
                'reason' => $mismatch,
                'expected_amount' => $payment->amount,
                'received_amount' => $transaction['amount'] ?? null,
                'received_currency' => $transaction['currency'] ?? null,
            ]);

            return $this->fail($payment, 'Payment did not match the amount due. Contact the organiser.', hold: true);
        }

        $payment->update([
            'status' => PaymentStatus::Successful,
            'provider_status' => $providerStatus,
            'transaction_id' => isset($transaction['id']) ? (string) $transaction['id'] : null,
            'flw_ref' => isset($transaction['flw_ref']) ? (string) $transaction['flw_ref'] : null,
            'payment_type' => $transaction['payment_type'] ?? null,
            'paid_at' => now(),
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'failure_reason' => null,
            'meta' => $this->extractMeta($transaction),
        ]);

        $payment = $payment->refresh();

        // Fulfilling here means the webhook, the redirect return and the admin
        // reconcile command all converge on the same behaviour.
        $this->fulfilment->handle($payment, $verifiedBy ?? 'gateway');

        return ['ok' => true, 'payment' => $payment, 'message' => 'Payment confirmed.'];
    }

    /**
     * Detect a mismatch between the expected and received transaction.
     *
     * Returns null when everything matches, or a short reason string.
     */
    private function detectMismatch(Payment $payment, array $transaction): ?string
    {
        if (($transaction['tx_ref'] ?? null) !== $payment->tx_ref) {
            return 'tx_ref mismatch';
        }

        $expected = $this->sentAmount($payment);
        $received = $transaction['amount'] ?? null;

        if ($received === null || (float) $received !== (float) $expected) {
            return 'amount mismatch';
        }

        $currency = $transaction['currency'] ?? null;

        if ($currency !== null && strtoupper((string) $currency) !== strtoupper($payment->currency)) {
            return 'currency mismatch';
        }

        return null;
    }

    /**
     * The exact numeric amount we sent to Flutterwave for this payment.
     *
     * Comparing against this — rather than re-deriving it — means fulfilment
     * is correct regardless of which unit convention Flutterwave uses.
     */
    public function sentAmount(Payment $payment): int|float
    {
        $amount = (float) $payment->amount;

        return config('services.flutterwave.amount_in_minor_units')
            ? (int) round($amount * 100)
            : (int) round($amount);
    }

    /**
     * @return array{ok: bool, payment: Payment, message: string}
     */
    private function fail(Payment $payment, string $reason, bool $hold = false): array
    {
        $payment->update([
            'status' => $hold ? PaymentStatus::Pending : PaymentStatus::Failed,
            'failure_reason' => $reason,
            'verified_at' => now(),
        ]);

        return ['ok' => false, 'payment' => $payment->refresh(), 'message' => $reason];
    }

    private function pendingPayment(Registration $registration): ?Payment
    {
        return $registration->payments()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->latest('id')
            ->first();
    }

    private function buildPayload(Registration $registration, Payment $payment): array
    {
        $event = $registration->event;

        return [
            'tx_ref' => $payment->tx_ref,
            'amount' => $this->sentAmount($payment),
            'currency' => $registration->currency,
            'redirect_url' => route('registration.payment.return', $registration->reference),
            'customer' => [
                'email' => $registration->email,
                'name' => $registration->name,
                'phone_number' => $registration->phone,
            ],
            'customizations' => [
                'title' => config('app.name'),
                'description' => Str::limit($event->title, 90),
            ],
            'meta' => [
                'registration_reference' => $registration->reference,
                'event_id' => $event->id,
                'event_title' => $event->title,
            ],
            'configurations' => [
                'session_duration' => (int) config('services.flutterwave.session_duration_minutes'),
                'max_retry_attempt' => 3,
            ],
        ];
    }

    private function extractMeta(array $transaction): ?array
    {
        if (empty($transaction['meta'])) {
            return null;
        }

        return is_array($transaction['meta']) ? $transaction['meta'] : null;
    }
}