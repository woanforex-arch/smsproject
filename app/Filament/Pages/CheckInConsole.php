<?php

namespace App\Filament\Pages;

use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\CheckInService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use UnitEnum;

class CheckInConsole extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.check-in-console';

    protected static bool $shouldRegisterNavigation = true;

    // --- Live state (public properties bind to the Livewire component) ---

    public ?string $eventId = null;

    public ?string $reference = '';

    public ?string $resultState = null; // success | warning | danger

    public ?string $resultMessage = null;

    public ?Ticket $resultTicket = null;

    /** @var array<int, string> */
    public array $recentReferences = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_active, 403);
    }

    public function checkIn(): void
    {
        $reference = trim($this->reference);

        if ($reference === '') {
            $this->setResult(danger: true, message: 'Enter a ticket reference to continue.');

            return;
        }

        $result = app(CheckInService::class)->checkInByReference(
            reference: $reference,
            user: auth()->user(),
            method: 'manual',
            ip: request()->ip(),
        );

        $this->resultTicket = $result['ticket'] ?? null;

        $this->setResult(
            danger: ! $result['ok'],
            warning: ($result['ok'] ?? false) === false && ($result['code'] ?? null) === 'already_checked_in',
            message: $result['message'],
        );

        if ($result['ok']) {
            $this->rememberRecent($result['ticket']->reference);
        }

        // Clear the input regardless so the operator can scan the next guest.
        $this->reference = '';

        $this->dispatch('checkin-complete');
    }

    public function undo(): void
    {
        if (! $this->resultTicket) {
            return;
        }

        $result = app(CheckInService::class)->undo($this->resultTicket, auth()->user());

        $this->resultTicket = $result['ticket'] ?? null;
        $this->setResult(
            danger: ! $result['ok'],
            message: $result['message'],
        );
    }

    protected function rememberRecent(string $reference): void
    {
        $recent = array_filter(array_merge([$reference], $this->recentReferences));

        $this->recentReferences = array_slice(array_values(array_unique($recent)), 0, 10);
    }

    protected function setResult(bool $danger = false, bool $warning = false, ?string $message = null): void
    {
        $this->resultState = $danger ? 'danger' : ($warning ? 'warning' : 'success');
        $this->resultMessage = $message;

        Notification::make()
            ->title($message)
            ->success($this->resultState === 'success')
            ->warning($this->resultState === 'warning')
            ->danger($this->resultState === 'danger')
            ->persistent($this->resultState !== 'success')
            ->send();
    }

    // --- Data for the view ---

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
        $checked = (clone $query)->where('status', TicketStatus::CheckedIn->value)->count();

        return [
            'total' => $total,
            'checked_in' => $checked,
            'remaining' => max(0, $total - $checked),
            'percent' => $total > 0 ? round($checked / $total * 100, 1) : 0.0,
        ];
    }

    public function getRecentCheckInsProperty()
    {
        $query = CheckIn::with(['ticket.event', 'user']);

        if ($this->eventId) {
            $query->whereHas('ticket', fn ($q) => $q->where('event_id', $this->eventId));
        }

        return $query->orderByDesc('created_at')->limit(8)->get();
    }
}