<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Stream a registration list as CSV, honouring the supplied filters. */
    public function registrations(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $this->logExport('registrations');

        $query = Registration::with(['event', 'ticketType', 'tickets'])
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('reference', 'like', $term));
            })
            ->orderBy('created_at');

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
                    fputcsv($handle, [
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
                    ]);
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
        $this->logExport('tickets');

        $query = Ticket::with(['event', 'ticketType', 'registration', 'checker'])
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->orderBy('event_id')
            ->orderBy('reference');

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
                    fputcsv($handle, [
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
                    ]);
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
        $this->logExport('event summary');

        $events = Event::withCount('tickets')
            ->withSum(['registrations as revenue' => fn ($q) => $q->where('status', 'verified')], 'amount')
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
                $sold = $event->tickets_count;
                $checkedIn = $event->tickets()->where('status', 'checked_in')->count();

                fputcsv($handle, [
                    $event->title,
                    $event->status->label(),
                    $event->starts_at?->toDateTimeString(),
                    $event->venue,
                    $event->capacity ?? '',
                    $sold,
                    $checkedIn,
                    max(0, $sold - $checkedIn),
                    $event->revenue,
                ]);
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
    $this->logExport('payments');

    $query = Payment::with(['registration.event', 'user'])
        ->when($request->filled('event_id'), fn ($q) => $q->whereHas('registration', fn ($sub) => $sub->where('event_id', $request->integer('event_id'))))
        ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
        ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
        ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
        ->orderBy('created_at');

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
                fputcsv($handle, [
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
                ]);
            }
        });

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

    /** Stream the full event list as CSV (the same columns accepted on import). */
    public function events(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $this->logExport('events');

        $query = Event::withCount('tickets')
            ->withSum(['registrations as revenue' => fn ($q) => $q->where('status', 'verified')], 'amount')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('starts_at', '<=', $request->date('to')->endOfDay()))
            ->orderBy('starts_at');

        $filename = 'events-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Title', 'Slug', 'Status', 'Starts At', 'Ends At', 'Registration Deadline',
                'Venue', 'Address', 'City', 'Capacity', 'Tickets Sold', 'Revenue', 'Created At',
            ]);

            $query->chunk(500, function ($events) use ($handle) {
                foreach ($events as $event) {
                    fputcsv($handle, [
                        $event->title,
                        $event->slug,
                        $event->status->label(),
                        $event->starts_at?->toDateTimeString(),
                        $event->ends_at?->toDateTimeString(),
                        $event->registration_deadline?->toDateTimeString(),
                        $event->venue,
                        $event->address,
                        $event->city,
                        $event->capacity ?? '',
                        $event->tickets_count,
                        $event->revenue,
                        $event->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Stream the staff account list as CSV. */
    public function users(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $this->logExport('staff accounts');

        $query = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name');

        $filename = 'staff-accounts-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Name', 'Email', 'Phone', 'Role', 'Active', 'Last Login', 'Created At',
            ]);

            $query->chunk(500, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->role->label(),
                        $user->is_active ? 'Yes' : 'No',
                        $user->last_login_at?->toDateTimeString(),
                        $user->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Download a ready-to-fill spreadsheet template for the event importer. */
    public function eventImportTemplate(): StreamedResponse
    {
        $this->authorizeAdmin();

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'title', 'slug', 'description', 'venue', 'address', 'city',
                'starts_at', 'ends_at', 'registration_deadline', 'status', 'capacity',
            ]);

            fputcsv($handle, [
                'Nairobi Tech Summit', 'nairobi-tech-summit', 'A one-day conference for builders.',
                'KICC', 'Harambee Avenue', 'Nairobi', '2026-07-18 09:00', '2026-07-18 17:00',
                '2026-07-15 23:59', 'draft', '500',
            ]);

            fclose($handle);
        }, 'events-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    /** Record that an export was downloaded. Never breaks the download. */
    protected function logExport(string $label): void
    {
        ActivityLog::record('export', null, 'Exported '.$label);
    }
}