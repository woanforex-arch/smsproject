<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Services\PaymentFulfilmentService;
use App\Services\PaymentService;
use App\Services\SmsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\KeyValueEntry;
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

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'tx_ref';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment')
                ->schema([
                    TextEntry::make('tx_ref')->label('Transaction reference')->weight('bold')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                        ->color(fn (PaymentStatus $state): string => $state->color()),
                    TextEntry::make('amount')
                        ->money(fn (Payment $record): string => $record->currency),
                    TextEntry::make('currency'),
                    TextEntry::make('payment_type')->label('Method')->placeholder('—'),
                    TextEntry::make('transaction_id')->copyable()->placeholder('—'),
                ])
                ->columns(3),

            Section::make('Customer')
                ->schema([
                    TextEntry::make('registration.reference')
                        ->label('Booking reference')
                        ->copyable()
                        ->url(fn (Payment $record): ?string => $record->registration
                            ? \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $record->registration])
                            : null),
                    TextEntry::make('registration.name'),
                    TextEntry::make('registration.email')->copyable(),
                    TextEntry::make('registration.phone')->copyable(),
                    TextEntry::make('registration.event.title')->label('Event'),
                    TextEntry::make('registration.quantity')->label('Tickets'),
                ])
                ->columns(3),

            Section::make('Timeline')
                ->schema([
                    TextEntry::make('created_at')->label('Created')->dateTime(),
                    TextEntry::make('paid_at')->label('Paid at')->dateTime()->placeholder('—'),
                    TextEntry::make('verified_at')->label('Verified at')->dateTime()->placeholder('—'),
                    TextEntry::make('verified_by')->label('Verified by')->placeholder('—'),
                    TextEntry::make('attempts')->label('Attempts')->numeric(),
                    TextEntry::make('failure_reason')->label('Failure reason')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(3),

            Section::make('Gateway payload')
                ->collapsible()
                ->collapsed()
                ->schema([
                    KeyValueEntry::make('raw_payload')
                        ->columnSpanFull()
                        ->placeholder('No raw payload stored.'),
                    KeyValueEntry::make('meta')
                        ->label('Meta')
                        ->columnSpanFull()
                        ->placeholder('No meta.'),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tx_ref')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('registration.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('registration.event.title')
                    ->label('Event')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('amount')
                    ->money(fn (Payment $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),

                TextColumn::make('payment_type')
                    ->label('Method')
                    ->formatStateUsing(fn (?string $state): string => $state ? strtoupper($state) : '—')
                    ->placeholder('—'),

                TextColumn::make('transaction_id')
                    ->label('Gateway ID')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('paid_at')->label('Paid')->dateTime()->sortable()->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PaymentStatus::options())
                    ->multiple(),

                SelectFilter::make('event')
                    ->relationship('registration.event', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_type')
                    ->label('Method')
                    ->options(fn (): array => Payment::query()
                        ->whereNotNull('payment_type')
                        ->distinct()
                        ->orderBy('payment_type')
                        ->pluck('payment_type', 'payment_type')
                        ->all())
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('reconcile')
                    ->label('Re-check with Flutterwave')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->action(function (Payment $record): void {
                        $result = app(PaymentService::class)->confirmByReference($record, 'manual_reconcile');

                        Notification::make()
                            ->title($result['message'])
                            ->{$result['ok'] ? 'success' : 'warning'}()
                            ->send();
                    }),

                Action::make('mark_paid')
                    ->label('Mark as paid manually')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (Payment $record): bool => ! $record->isPaid() && $record->status !== PaymentStatus::Refunded)
                    ->schema([
                        Textarea::make('note')
                            ->label('Note (optional)')
                            ->helperText('Use for cash or bank transfers where no webhook is expected.')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Payment $record, array $data): void {
                        app(PaymentFulfilmentService::class)
                            ->markPaidManually($record, auth()->user(), $data['note'] ?? null);

                        Notification::make()->title('Payment marked as paid and tickets issued.')->success()->send();
                    }),

                Action::make('resend_checkout')
                    ->label('Resend payment link')
                    ->icon('heroicon-m-link')
                    ->color('gray')
                    ->visible(fn (Payment $record): bool => $record->isPending())
                    ->action(function (Payment $record): void {
                        $link = $record->checkout_url;

                        if (! $link) {
                            Notification::make()->title('No checkout link has been generated yet.')->warning()->send();

                            return;
                        }

                        // Re-sending is just giving the guest their link again.
                        app(SmsService::class)->send(
                            toPhone: $record->registration?->phone ?? '',
                            body: "Pay for your booking {$record->registration?->reference}: {$link}",
                            type: 'payment_link',
                            registrationId: $record->registration_id,
                        );

                        Notification::make()->title('Payment link sent by SMS.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('reconcile_selected')
                        ->label('Re-check selected')
                        ->icon('heroicon-m-arrow-path')
                        ->color('primary')
                        ->action(function ($records): void {
                            $service = app(PaymentService::class);
                            $ok = 0;

                            foreach ($records as $record) {
                                if ($service->confirmByReference($record, 'manual_reconcile')['ok']) {
                                    $ok++;
                                }
                            }

                            Notification::make()->title("Confirmed {$ok} payment(s).")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['registration.event']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}