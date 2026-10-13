<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Ticket types';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ticket')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('currency')
                        ->required()
                        ->maxLength(3)
                        ->default(fn (): string => Money::code()),
                    TextInput::make('price')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->live(onBlur: true)
                        ->helperText('Set to 0 for a free ticket — payment is then skipped entirely.'),

                    Toggle::make('requires_payment')
                        ->default(true)
                        ->helperText('Charge via Flutterwave for this tier. Ignored when the price is 0.'),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Inventory')
                ->schema([
                    TextInput::make('quantity_total')
                        ->label('Total quantity')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Set to 0 for unlimited availability.'),
                    TextInput::make('quantity_per_order_max')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(10)
                        ->helperText('Maximum per single order.'),
                    DateTimePicker::make('sales_start_at')
                        ->seconds(false)
                        ->helperText('Leave blank to sell immediately.'),
                    DateTimePicker::make('sales_end_at')
                        ->seconds(false)
                        ->helperText('Leave blank to sell until the event ends.'),
                    Toggle::make('is_active')
                        ->default(true),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first.'),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): ?string => $record->description
                        ? Str::limit($record->description, 70)
                        : null),

                TextColumn::make('price')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),

                TextColumn::make('quantity_sold')
                    ->label('Sold')
                    ->numeric(),

                TextColumn::make('quantity_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($record): string => $record->quantity_total === 0 ? 'Unlimited' : (string) $record->quantity_total),

                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record): string => $record->quantity_total === 0
                        ? '∞'
                        : (string) $record->quantityRemaining())
                    ->badge()
                    ->color(fn ($record): string => $record->isSoldOut() ? 'danger' : 'success'),

                TextColumn::make('sales_start_at')
                    ->label('Sales start')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sales_end_at')
                    ->label('Sales end')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add ticket type'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn ($record): bool => $record->quantity_sold > 0)
                    ->tooltip(fn ($record): ?string => $record->quantity_sold > 0
                        ? 'Cannot delete a ticket type that has already sold tickets.'
                        : null),
            ]);
    }
}