<?php

namespace App\Http\Controllers\Site;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    /** Show a single ticket on screen. */
    public function show(Ticket $ticket): View
    {
        $ticket->load(['event', 'ticketType', 'registration']);

        abort_if($ticket->status === TicketStatus::Cancelled, 404);

        $siblings = $ticket->registration?->tickets ?? collect();

        return view('public.tickets.show', compact('ticket', 'siblings'));
    }

    /** Show the full printable e-ticket for every ticket in a booking. */
    public function print(string $reference): View
    {
        $ticket = Ticket::with(['event', 'ticketType', 'registration.tickets'])
            ->where('reference', $reference)
            ->firstOrFail();

        $tickets = $ticket->registration?->tickets ?? collect([$ticket]);

        return view('public.tickets.print', [
            'registration' => $ticket->registration,
            'tickets' => $tickets,
            'event' => $ticket->event,
        ]);
    }

    /** Find a registration by email + reference so guests can recover their tickets. */
    public function lookup(): View
    {
        return view('public.tickets.lookup');
    }

    public function find(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
        ]);

        $ticket = Ticket::where('reference', $data['reference'])->first();

        if (! $ticket) {
            return back()->withErrors(['reference' => 'No ticket found with that reference.']);
        }

        return redirect()->route('tickets.show', $ticket->reference);
    }
}