<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Services\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function create(Request $request, Event $event, string $ticketType = null): View
    {
        abort_unless($event->isOpenForRegistration(), 404);

        $ticketTypes = $event->ticketTypes->filter(fn (TicketType $type) => $type->isOnSale())->values();

        if ($ticketTypes->isEmpty()) {
            return view('public.events.sold-out', compact('event'));
        }

        $selected = $ticketType
            ? $ticketTypes->firstWhere('id', (int) $ticketType) ?? $ticketTypes->first()
            : $ticketTypes->first();

        return view('public.registration.create', [
            'event' => $event,
            'ticketTypes' => $ticketTypes,
            'selected' => $selected,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->throttle($request);

        $validated = $request->validate([
            'ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $ticketType = TicketType::findOrFail($validated['ticket_type_id']);

        if ($ticketType->event_id !== $event->id) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'That ticket type does not belong to this event.',
            ]);
        }

        $registration = $this->registrations->createPending($event, $ticketType, $validated);

        return redirect()
            ->route('registration.verify', $registration->reference)
            ->with('status', 'We sent a verification code to '.$registration->phone.'.');
    }

    public function verifyForm(Registration $registration): View
    {
        return view('public.registration.verify', compact('registration'));
    }

    public function verify(Request $request, Registration $registration): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'min:'.config('ticketing.otp.length'), 'max:'.config('ticketing.otp.length')],
        ]);

        $registration = $this->registrations->verifyOtp($registration, (string) $request->string('code'));

        // Paid bookings must settle before tickets are released.
        if ($registration->requiresPayment()) {
            return redirect()
                ->route('registration.pay', $registration->reference)
                ->with('status', 'Your phone is verified. Complete payment to release your tickets.');
        }

        $first = $registration->tickets->first();

        return redirect()
            ->route('tickets.show', $first?->reference)
            ->with('status', 'Your tickets are ready.');
    }

    public function resend(Registration $registration): RedirectResponse
    {
        $this->registrations->resendOtp($registration);

        return back()->with('status', 'A new verification code has been sent.');
    }

    protected function throttle(Request $request): void
    {
        $key = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'phone' => 'Too many registration attempts. Please try again later.',
            ])->status(429);
        }

        RateLimiter::hit($key, 300);
    }
}