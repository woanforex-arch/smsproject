<?php

namespace App\Filament\Resources\Registrations;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Registrant')
                ->schema([
                    TextEntry::make('reference')->weight('bold')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                        ->color(fn (RegistrationStatus $state): string => $state->color()),
                    TextEntry::make('name'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('phone')->copyable(),
                ])
                ->columns(3),

            Section::make('Order')
                ->schema([
                    TextEntry::make('event.title')->label('Event'),
                    TextEntry::make('ticketType.name')->label('Ticket type'),
                    TextEntry::make('quantity')->numeric(),
                    TextEntry::make('amount')
                        ->formatStateUsing(fn ($state): string => Money::format($state)),
                    TextEntry::make('payment_status')
                        ->label('Payment')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'paid' => 'Paid',
                            'pending' => 'Unpaid',
                            default => 'Not required',
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('paid_at')
                        ->label('Paid at')
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('otp_verified_at')
                        ->label('Verified at')
                        ->dateTime()
                        ->placeholder('Not verified'),
                    TextEntry::make('created_at')
                        ->label('Registered')
                        ->dateTime(),
                ])
                ->columns(4),

            Section::make('Issued tickets')
                ->schema([
                    TextEntry::make('tickets')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->limitList(50)
                        ->state(fn (Registration $record): array => $record->tickets
                            ->map(fn ($t) => $t->reference.' — '.$t->ticketType->name.' ('.$t->status->label().')')
                            ->all())
                        ->placeholder('No tickets issued yet.'),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('SMS history')
                ->schema([
                    TextEntry::make('smsMessages')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->limitList(20)
                        ->state(fn (Registration $record): array => $record->smsMessages
                            ->latest()
                            ->limit(20)
                            ->get()
                            ->map(fn ($s) => sprintf(
                                '%s · %s · %s%s',
                                $s->created_at->format('j M H:i'),
                                $s->type,
                                $s->status->label(),
                                $s->error_message ? ' — '.$s->error_message : ''
                            ))
                            ->all())
                        ->placeholder('No SMS messages sent.'),
                ])
                ->columns(1)
                ->columnSpanFull(),
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

                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('email')->label('Email')->searchable()->copyable()->toggleable(),

                TextColumn::make('phone')->label('Phone')->searchable()->copyable(),

                TextColumn::make('event.title')->label('Event')->searchable()->sortable()->limit(35),

                TextColumn::make('ticketType.name')->label('Type')->toggleable(),

                TextColumn::make('quantity')->numeric()->sortable(),

                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Paid',
                        'pending' => 'Unpaid',
                        default => 'Not required',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->numeric(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->relationship('event', 'title')->searchable()->preload(),
                SelectFilter::make('ticket_type')->relationship('ticketType', 'name')->searchable()->preload(),
                SelectFilter::make('status')
                    ->options(collect(RegistrationStatus::cases())
                        ->mapWithKeys(fn (RegistrationStatus $c) => [$c->value => $c->label()])
                        ->all())
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Unpaid',
                        'not_required' => 'Not required',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('resend_sms')
                    ->label('Resend ticket SMS')
                    ->icon('heroicon-m-device-phone-mobile')
                    ->color('primary')
                    ->visible(fn (Registration $record): bool => $record->isVerified())
                    ->action(function (Registration $record): void {
                        try {
                            app(RegistrationService::class)->resendTicketSms($record);

                            Notification::make()
                                ->title('Ticket reference sent by SMS.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('resend_sms_bulk')
                        ->label('Resend ticket SMS')
                        ->icon('heroicon-m-device-phone-mobile')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $service = app(RegistrationService::class);
                            $sent = 0;

                            foreach ($records as $record) {
                                if (! $record->isVerified()) {
                                    continue;
                                }

                                try {
                                    $service->resendTicketSms($record);
                                    $sent++;
                                } catch (\Throwable) {
                                    // Skip failures; the audit log records them.
                                }
                            }

                            Notification::make()
                                ->title("Ticket SMS sent for {$sent} registration(s).")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['event', 'ticketType', 'tickets', 'smsMessages']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'view' => ViewRegistration::route('/{record}'),
        ];
    }
}
