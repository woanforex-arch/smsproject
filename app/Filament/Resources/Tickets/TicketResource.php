<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Services\CheckInService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ticket')
                ->schema([
                    TextEntry::make('reference')
                        ->weight('bold')
                        ->copyable()
                        ->columnSpanFull(),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                        ->color(fn (TicketStatus $state): string => $state->color()),

                    TextEntry::make('attendee_name')->label('Attendee'),
                    TextEntry::make('attendee_email')->label('Email')->placeholder('—'),
                    TextEntry::make('attendee_phone')->label('Phone')->placeholder('—'),
                    TextEntry::make('ticketType.name')->label('Ticket type'),
                    TextEntry::make('event.title')->label('Event'),
                ])
                ->columns(3),

            Section::make('QR code')
                ->description('Scan this code at the door, or use the reference above for manual entry.')
                ->schema([
                    ImageEntry::make('qr')
                        ->label('')
                        ->height(240)
                        ->state(fn (Ticket $record): HtmlString => new HtmlString(
                            QrCode::format('svg')
                                ->size(240)
                                ->margin(1)
                                ->generate($record->qrPayload())
                        )),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Check-in')
                ->schema([
                    TextEntry::make('checked_in_at')
                        ->label('Checked in at')
                        ->dateTime()
                        ->placeholder('Not checked in'),
                    TextEntry::make('checker.name')->label('Checked in by')->placeholder('—'),
                    TextEntry::make('check_in_method')
                        ->label('Method')
                        ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—')
                        ->placeholder('—'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('attendee_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('ticketType.name')->label('Type')->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                    ->color(fn (TicketStatus $state): string => $state->color()),

                TextColumn::make('checked_in_at')
                    ->label('Checked in')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('check_in_method')
                    ->label('Method')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ticket_type')
                    ->relationship('ticketType', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(TicketStatus::options())
                    ->multiple(),

                Filter::make('not_checked_in')
                    ->label('Not yet checked in')
                    ->query(fn (Builder $query): Builder => $query->where('status', TicketStatus::Valid->value)),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('check_in')
                    ->label('Check in')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (Ticket $record): bool => $record->status === TicketStatus::Valid)
                    ->requiresConfirmation()
                    ->action(function (Ticket $record): void {
                        $result = app(CheckInService::class)->checkInByReference(
                            reference: $record->reference,
                            user: auth()->user(),
                            method: 'manual',
                            ip: request()->ip(),
                        );

                        $this->notify($result['ok'] ? 'success' : 'danger', title: $result['message']);
                    }),

                Action::make('undo')
                    ->label('Undo check-in')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Ticket $record): bool => $record->status === TicketStatus::CheckedIn)
                    ->requiresConfirmation()
                    ->action(function (Ticket $record): void {
                        $result = app(CheckInService::class)->undo($record, auth()->user());

                        $this->notify($result['ok'] ? 'success' : 'danger', title: $result['message']);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('mark_cancelled')
                        ->label('Cancel selected tickets')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(fn (Ticket $t) => $t->update(['status' => TicketStatus::Cancelled]));
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['event', 'ticketType', 'checker']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}