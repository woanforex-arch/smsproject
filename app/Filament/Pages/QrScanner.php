<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\CheckInService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use UnitEnum;

class QrScanner extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.qr-scanner';

    public ?string $eventId = null;

    /** Used by the manual-entry fallback form. */
    public string $reference = '';

    /** Result banner state for the last scan. */
    public ?string $resultState = null;

    public ?string $resultMessage = null;

    public ?array $resultTicket = null;

    /** Live feed of the most recent scans in this session. */
    public array $feed = [];

    #[On('scan')]
    public function scan(string $payload): void
    {
        $payload = trim($payload);

        if ($payload === '') {
            return;
        }

        // Throttle: one scan per scanner per second is plenty at a busy door.
        $key = 'scan:'.auth()->id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->setBanner('danger', 'Too many scans — slow down.');

            return;
        }

        RateLimiter::hit($key, 10);

        $service = app(CheckInService::class);
        $result = $service->checkInByQrPayload($payload, auth()->user(), request()->ip());

        $ticket = $result['ticket'] ?? null;

        $this->resultTicket = $ticket ? [
            'reference' => $ticket->reference,
            'attendee' => $ticket->attendee_name,
            'event' => $ticket->event->title ?? '',
            'type' => $ticket->ticketType->name ?? '',
            'status' => $ticket->status->label(),
        ] : null;

        $this->setBanner(
            $result['ok'] ? 'success' : (($result['code'] ?? '') === 'already_checked_in' ? 'warning' : 'danger'),
            $result['message'],
            $result['ok'],
        );

        $this->pushFeed($result);
    }

    /** Manual-entry fallback: scan the reference typed into the form. */
    public function scanManual(): void
    {
        $this->scan($this->reference);
    }

    protected function pushFeed(array $result): void
    {
        $ticket = $result['ticket'] ?? null;

        if (! $ticket) {
            return;
        }

        array_unshift($this->feed, [
            'reference' => $ticket->reference,
            'attendee' => $ticket->attendee_name,
            'ok' => (bool) $result['ok'],
            'message' => $result['message'],
            'at' => now()->format('H:i:s'),
        ]);

        $limit = (int) config('ticketing.checkin.scanner_feed_limit', 50);
        $this->feed = array_slice($this->feed, 0, $limit);
    }

    protected function setBanner(string $state, ?string $message, bool $success = false): void
    {
        $this->resultState = $state;
        $this->resultMessage = $message;

        if (! $success) {
            Notification::make()
                ->title($message)
                ->danger($state === 'danger')
                ->warning($state === 'warning')
                ->persistent()
                ->send();
        }
    }

    public function undoLast(): void
    {
        $last = $this->feed[0] ?? null;

        if (! $last) {
            return;
        }

        $ticket = Ticket::where('reference', $last['reference'])->first();

        if (! $ticket) {
            return;
        }

        $result = app(CheckInService::class)->undo($ticket, auth()->user());

        if ($result['ok'] && isset($this->feed[0])) {
            $this->feed[0]['ok'] = false;
            $this->feed[0]['message'] = 'Check-in reversed.';
        }

        $this->setBanner($result['ok'] ? 'success' : 'danger', $result['message']);
    }

    public function getEventsProperty()
    {
        return Event::query()
            ->where('status', '!=', 'draft')
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at']);
    }

    public function getSummaryProperty(): array
    {
        $query = Ticket::query();

        if ($this->eventId) {
            $query->where('event_id', $this->eventId);
        }

        $total = (clone $query)->whereIn('status', ['valid', 'checked_in'])->count();
        $checked = (clone $query)->where('status', 'checked_in')->count();

        return [
            'total' => $total,
            'checked_in' => $checked,
            'remaining' => max(0, $total - $checked),
        ];
    }
}