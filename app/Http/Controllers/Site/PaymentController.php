<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\PaymentGatewayException;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    /**
     * Show the "pay now" page for a registration.
     */
    public function show(Registration $registration): View|RedirectResponse
    {
        $registration->loadMissing(['event', 'ticketType', 'tickets']);

        // The booking must be phone-verified before anything payment-related.
        // This check comes first so an unverified (e.g. free) booking is not
        // bounced back here forever by result().
        abort_unless($registration->isVerified(), 403, 'Verify your phone number first.');

        if (! $registration->requiresPayment() || $registration->isPaid()) {
            return $this->result($registration);
        }

        return view('public.payment.pay', ['registration' => $registration]);
    }

    /**
     * Generate a checkout link and hand the guest over to Flutterwave.
     */
    public function checkout(Registration $registration): RedirectResponse
    {
        abort_unless($registration->isVerified(), 403);
        abort_unless($registration->requiresPayment(), 404);

        if ($registration->isPaid()) {
            return $this->result($registration);
        }

        try {
            $payment = $this->payments->initiate($registration);
        } catch (PaymentGatewayException $e) {
            Log::error('Could not start Flutterwave checkout', [
                'registration' => $registration->reference,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('registration.pay', $registration->reference)
                ->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->away($payment->checkout_url);
    }

    /**
     * Where Flutterwave sends the guest back to.
     *
     * The query string is never trusted: it is only used as a hint, and the
     * authoritative check is a fresh verification against the gateway using
     * our own stored tx_ref.
     */
    public function return(Request $request, Registration $registration): RedirectResponse
    {
        $registration->loadMissing(['event', 'ticketType', 'tickets']);

        abort_unless($registration->isVerified(), 403);

        if ($registration->isPaid()) {
            return $this->result($registration);
        }

        $payment = $registration->latestPayment;

        if (! $payment) {
            return redirect()
                ->route('registration.pay', $registration->reference)
                ->withErrors(['payment' => 'No payment has been started yet.']);
        }

        $result = $this->payments->confirmByReference($payment, 'redirect_return');

        if ($result['ok']) {
            // PaymentService already fulfilled the booking.
            return $this->result($registration->fresh());
        }

        return redirect()
            ->route('registration.pay', $registration->reference)
            ->withErrors(['payment' => $result['message']]);
    }

    /**
     * Guest cancelled at the Flutterwave page or the session expired.
     */
    public function cancelled(Registration $registration): RedirectResponse
    {
        // If the payment actually settled, treat "cancel" as a stale browser
        // action and send the guest to their tickets instead of stranding a
        // successful payment.
        if ($registration->isPaid()) {
            return $this->result($registration->fresh());
        }

        $payment = $registration->latestPayment;

        if ($payment?->canBePaid()) {
            $payment->update([
                'status' => \App\Enums\PaymentStatus::Cancelled,
                'failure_reason' => 'Cancelled by the customer.',
                'verified_at' => now(),
            ]);
        }

        return redirect()
            ->route('registration.pay', $registration->reference)
            ->withErrors(['payment' => 'Payment was cancelled. Your booking is still held — you can pay again.']);
    }

    /**
     * Send the guest to their tickets once the booking is complete.
     */
    private function result(Registration $registration): RedirectResponse
    {
        if (! $registration->isComplete()) {
            // Deliberately NOT back to the pay page: for a free booking that
            // would create an infinite redirect loop.
            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Your booking is still being confirmed. Please check back in a moment.']);
        }

        $first = $registration->tickets->first();

        return redirect()->route('tickets.show', $first?->reference);
    }
}