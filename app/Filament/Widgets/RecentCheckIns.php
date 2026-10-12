<?php

namespace App\Filament\Widgets;

use App\Models\CheckIn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentCheckIns extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Recent check-ins';

    /**
     * Sits beside the "Tickets sold by event" chart.
     *
     * The dashboard renders widgets in a 2-column grid, and this widget's
     * parent class already defaults to a span of 1. Setting it to 'full' here
     * was what pushed it onto its own row.
     */
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CheckIn::query()->with(['ticket.event', 'ticket.ticketType', 'user'])
            )
            ->columns([
                TextColumn::make('ticket.attendee_name')
                    ->label('Attendee')
                    ->searchable()
                    ->description(fn (CheckIn $record): ?string => $record->ticket?->reference)
                    ->weight('bold')
                    ->wrap(false),

                TextColumn::make('ticket.event.title')
                    ->label('Event')
                    ->limit(24)
                    ->toggleable(),

                TextColumn::make('method')
                    ->label('Via')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color(fn (string $state): string => $state === 'qr' ? 'info' : 'gray')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // Keeps the column visually level with the chart beside it.
            ->paginated([5, 10, 25]);
    }
}