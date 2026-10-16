<?php

namespace App\Filament\Widgets;

use App\Enums\EventStatus;
use App\Models\Event;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class UpcomingEventsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Tickets sold by event';

    protected ?string $description = 'Sold vs. checked-in for upcoming events.';

    /**
     * Sits beside the "Recent check-ins" table. The dashboard grid is two
     * columns wide and Widget already defaults to a span of 1; this is set
     * explicitly so the pairing is obvious to anyone editing this later.
     */
    protected int|string|array $columnSpan = 1;

    /** Keeps this widget roughly level with the table beside it. */
    protected ?string $maxHeight = '360px';

    protected ?array $data = [];

    protected ?array $options = [
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'ticks' => ['precision' => 0],
            ],
        ],
        'plugins' => [
            'legend' => ['display' => true],
        ],
    ];

    public function getData(): array
    {
        $this->data = $this->buildData();

        return $this->data;
    }

    protected function buildData(): array
    {
        $events = Event::query()
            ->published()
            ->where('ends_at', '>=', now())
            ->withCount([
                'tickets as sold' => fn ($q) => $q->whereIn('status', ['valid', 'checked_in']),
                'tickets as checked' => fn ($q) => $q->where('status', 'checked_in'),
            ])
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        if ($events->isEmpty()) {
            $this->heading = 'Tickets sold by event';
            $this->description = 'No upcoming published events yet.';
        }

        return [
            'labels' => $events->map(fn (Event $event) => $this->shortLabel($event))->all(),
            'datasets' => [
                [
                    'label' => 'Sold',
                    'data' => $events->map(fn (Event $event) => (int) $event->sold)->all(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.7)',
                    'borderColor' => 'rgb(245, 158, 11)',
                ],
                [
                    'label' => 'Checked in',
                    'data' => $events->map(fn (Event $event) => (int) $event->checked)->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
            ],
        ];
    }

    private function shortLabel(Event $event): string
    {
        return Str::limit($event->title, 22);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}