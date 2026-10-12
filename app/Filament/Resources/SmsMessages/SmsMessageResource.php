<?php

namespace App\Filament\Resources\SmsMessages;

use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SmsMessageResource extends Resource
{
    protected static ?string $model = SmsMessage::class;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'SMS log';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'to_phone';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('to_phone')
                    ->label('To')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('body')
                    ->label('Message')
                    ->limit(60)
                    ->tooltip(fn (SmsMessage $record): string => $record->body),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (SmsStatus $state): string => $state->label())
                    ->color(fn (SmsStatus $state): string => $state->color()),

                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('provider_sid')
                    ->label('Twilio SID')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SmsStatus::cases())
                        ->mapWithKeys(fn (SmsStatus $c) => [$c->value => $c->label()])
                        ->all())
                    ->multiple(),

                SelectFilter::make('type')
                    ->options([
                        'otp' => 'OTP',
                        'ticket_reference' => 'Ticket reference',
                    ])
                    ->multiple(),

                Filter::make('failed_only')
                    ->label('Failures only')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', [
                        SmsStatus::Failed->value,
                        SmsStatus::Undelivered->value,
                    ])),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (SmsMessage $record): void {
                        $result = app(\App\Services\SmsService::class)->resend($record);

                        $this->notify(
                            $result->status->isFailure() ? 'danger' : 'success',
                            title: $result->status->isFailure()
                                ? 'Resend failed: '.$result->error_message
                                : 'Message resent.',
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('resend_selected')
                        ->label('Resend selected')
                        ->icon('heroicon-m-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $service = app(\App\Services\SmsService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                $service->resend($record);
                                $count++;
                            }

                            $this->notify('success', title: "Resent {$count} message(s).");
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsMessages::route('/'),
        ];
    }
}