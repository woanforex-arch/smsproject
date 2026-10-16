<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Stream a registration list as CSV, honouring the supplied filters. */
    public function registrations(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $this->validateFilters($request);

        $query = Registration::with(['event', 'ticketType', 'tickets'])
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$this->escapeLike((string) $request->string('q')).'%';
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->orderBy('created_at')
            ->orderBy('id');

        $filename = 'registrations-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Registration Ref', 'Name', 'Email', 'Phone', 'Event', 'Ticket Type',
                'Quantity', 'Amount', 'Currency', 'Status', 'Payment Status',
                'Paid At', 'Ticket References', 'Registered At', 'Verified At',
            ]);

            $query->chunk(500, function ($registrations) use ($handle) {
                foreach ($registrations as $registration) {
                    fputcsv($handle, array_map($this->csvSafe(...), [
                        $registration->reference,
                        $registration->name,
                        $registration->email,
                        $registration->phone,
                        $registration->event?->title,
                        $registration->ticketType?->name,
                        $registration->quantity,
                        $registration->amount,
                        $registration->currency,
                        $registration->status->label(),
                        match ($registration->payment_status) {
                            'paid' => 'Paid',
                            'pending' => 'Unpaid',
                            default => 'Not required',
                        },
                        $registration->paid_at?->toDateTimeString(),
                        $registration->tickets->pluck('reference')->implode(' '),
                        $registration->created_at?->toDateTimeString(),
                        $registration->otp_verified_at?->toDateTimeString(),
                    ]));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Stream the issued-ticket list (attendance sheet) as CSV. */
    public function tickets(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $this->validateFilters($request);

        $query = Ticket::with(['event', 'ticketType', 'registration', 'checker'])
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->orderBy('event_id')
            ->orderBy('reference')
            ->orderBy('id');

        $filename = 'tickets-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Ticket Ref', 'Attendee', 'Email', 'Phone', 'Event', 'Ticket Type',
                'Registration Ref', 'Status', 'Checked In At', 'Checked In By',
                'Check-in Method', 'Issued At',
            ]);

            $query->chunk(500, function ($tickets) use ($handle) {
                foreach ($tickets as $ticket) {
                    fputcsv($handle, array_map($this->csvSafe(...), [
                        $ticket->reference,
                        $ticket->attendee_name,
                        $ticket->attendee_email,
                        $ticket->attendee_phone,
                        $ticket->event?->title,
                        $ticket->ticketType?->name,
                        $ticket->registration?->reference,
                        $ticket->status->label(),
                        $ticket->checked_in_at?->toDateTimeString(),
                        $ticket->checker?->name,
                        $ticket->check_in_method,
                        $ticket->created_at?->toDateTimeString(),
                    ]));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Per-event summary of sales and attendance. */
    public function summary(): StreamedResponse
    {
        $this->authorizeAdmin();

        $events = Event::withCount(['tickets as sold' => fn ($q) => $q->whereIn('status', ['valid', 'checked_in'])])
            ->withSum(['registrations as revenue' => fn ($q) => $q->where('status', 'verified')->where('payment_status', 'paid')], 'amount')
            ->orderBy('starts_at')
            ->get();

        $filename = 'event-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($events) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Event', 'Status', 'Starts At', 'Venue', 'Capacity',
                'Tickets Sold', 'Checked In', 'Outstanding', 'Revenue',
            ]);

            foreach ($events as $event) {
                $sold = $event->sold;
                $checkedIn = $event->tickets()->where('status', 'checked_in')->count();

                fputcsv($handle, array_map($this->csvSafe(...), [
                    $event->title,
                    $event->status->label(),
                    $event->starts_at?->toDateTimeString(),
                    $event->venue,
                    $event->capacity ?? '',
                    $sold,
                    $checkedIn,
                    max(0, $sold - $checkedIn),
                    $event->revenue,
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Stream the payments ledger as CSV. */
public function payments(Request $request): StreamedResponse
{
    $this->authorizeAdmin();
    $this->validateFilters($request);

    $query = Payment::with(['registration.event', 'user'])
        ->when($request->filled('event_id'), fn ($q) => $q->whereHas('registration', fn ($sub) => $sub->where('event_id', $request->integer('event_id'))))
        ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
        ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
        ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
        ->orderBy('created_at')
        ->orderBy('id');

    $filename = 'payments-'.now()->format('Y-m-d-His').'.csv';

    return response()->streamDownload(function () use ($query) {
        $handle = fopen('php://output', 'w');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Tx Ref', 'Gateway Transaction ID', 'Status', 'Provider Status', 'Method',
            'Amount', 'Currency', 'Booking Ref', 'Customer', 'Email', 'Phone', 'Event',
            'Tickets Issued', 'Paid At', 'Verified By', 'Failure Reason', 'Created At',
        ]);

        $query->chunk(500, function ($payments) use ($handle) {
            foreach ($payments as $payment) {
                fputcsv($handle, array_map($this->csvSafe(...), [
                    $payment->tx_ref,
                    $payment->transaction_id ?? '',
                    $payment->status->label(),
                    $payment->provider_status ?? '',
                    $payment->payment_type ?? '',
                    $payment->amount,
                    $payment->currency,
                    $payment->registration?->reference ?? '',
                    $payment->registration?->name ?? '',
                    $payment->registration?->email ?? '',
                    $payment->registration?->phone ?? '',
                    $payment->registration?->event?->title ?? '',
                    $payment->registration?->tickets?->count() ?? 0,
                    $payment->paid_at?->toDateTimeString(),
                    $payment->verified_by ?? '',
                    $payment->failure_reason ?? '',
                    $payment->created_at?->toDateTimeString(),
                ]));
            }
        });

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    /** Reject malformed filter values instead of letting ->date() throw a 500. */
    protected function validateFilters(Request $request): void
    {
        $request->validate([
            'event_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /**
     * Neutralise spreadsheet formula injection: a cell whose first character
     * is =, +, -, @ (or a control char) is treated as a formula by Excel et al.
     */
    protected function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }

    /** Escape LIKE wildcards so user input cannot widen the search. */
    protected function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}