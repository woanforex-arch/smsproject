<?php

namespace App\Filament\Resources\Events;

use App\Enums\EventStatus;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\RelationManagers\TicketTypesRelationManager;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Events';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event details')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, callable $set) {
                            if ($operation === 'create') {
                                $set('slug', Event::uniqueSlug($state));
                            }
                        })
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in the public event URL.'),

                    Select::make('status')
                        ->options(EventStatus::options())
                        ->required()
                        ->default(EventStatus::Draft->value),

                    Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Location')
                ->schema([
                    TextInput::make('venue')->maxLength(255),
                    TextInput::make('address')->maxLength(255),
                    TextInput::make('city')->maxLength(120),
                ])
                ->columns(3),

            Section::make('Schedule')
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->required()
                        ->seconds(false),
                    DateTimePicker::make('ends_at')
                        ->required()
                        ->seconds(false)
                        ->after('starts_at'),
                    DateTimePicker::make('registration_deadline')
                        ->seconds(false)
                        ->helperText('Leave blank to accept registrations until the event ends.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Capacity & media')
                ->schema([
                    TextInput::make('capacity')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Overall event cap. Leave blank for unlimited.'),

                    FileUpload::make('banner_path')
                        ->image()
                        ->directory('event-banners')
                        ->visibility('public')
                        ->imageEditor(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('title')
                        ->columnSpanFull(),
                    \Filament\Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                        ->color(fn (EventStatus $state): string => $state->color()),
                    \Filament\Infolists\Components\TextEntry::make('slug'),
                    \Filament\Infolists\Components\TextEntry::make('description')
                        ->placeholder('No description.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Schedule')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('starts_at')->label('Starts')->dateTime(),
                    \Filament\Infolists\Components\TextEntry::make('ends_at')->label('Ends')->dateTime(),
                    \Filament\Infolists\Components\TextEntry::make('registration_deadline')
                        ->label('Registration closes')
                        ->dateTime()
                        ->placeholder('Until event ends'),
                    \Filament\Infolists\Components\TextEntry::make('capacity')
                        ->label('Capacity')
                        ->placeholder('Unlimited'),
                ])
                ->columns(3),

            Section::make('Location')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('venue')->placeholder('—'),
                    \Filament\Infolists\Components\TextEntry::make('city')->placeholder('—'),
                    \Filament\Infolists\Components\TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Sales snapshot')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('sold')
                        ->label('Tickets sold')
                        ->state(fn (Event $record): int => $record->soldCount()),
                    \Filament\Infolists\Components\TextEntry::make('checked_in')
                        ->label('Checked in')
                        ->state(fn (Event $record): int => $record->checkedInCount()),
                    \Filament\Infolists\Components\TextEntry::make('total_capacity')
                        ->label('Ticket allocation')
                        ->state(fn (Event $record): int => $record->totalCapacity()),
                    \Filament\Infolists\Components\TextEntry::make('revenue')
                        ->label('Revenue')
                        ->money(fn (Event $record): string => $record->currency())
                        ->state(fn (Event $record): float => $record->revenue()),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Event $record): ?string => $record->venue ?: null)
                    ->limit(60),

                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                    ->color(fn (EventStatus $state): string => $state->color()),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('sold')
                    ->label('Sold')
                    ->numeric()
                    ->state(fn (Event $record): int => $record->soldCount()),

                TextColumn::make('checked_in')
                    ->label('Checked in')
                    ->numeric()
                    ->state(fn (Event $record): int => $record->checkedInCount()),

                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->money(fn (Event $record): string => $record->currency())
                    ->state(fn (Event $record): float => $record->revenue()),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EventStatus::options())
                    ->multiple(),

                TernaryFilter::make('registration_open')
                    ->label('Registration open')
                    ->query(function (Builder $query, array $data): Builder {
                        $state = $data['state'] ?? null;

                        return match ($state) {
                            true => $query->where('status', EventStatus::Published->value)
                                ->where(fn ($q) => $q
                                    ->whereNull('registration_deadline')
                                    ->orWhere('registration_deadline', '>=', now())),
                            false => $query->where('status', '!=', EventStatus::Published->value),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                RestoreAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                    ForceDeleteBulkAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Full ticket types are loaded (not a column subset) because the
            // relation is also read for names/prices elsewhere in the panel.
            ->with('ticketTypes')
            ->withCount('tickets')
            ->withSum('registrations as revenue', 'amount');
    }

    public static function getRelations(): array
    {
        return [
            TicketTypesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'view' => ViewEvent::route('/{record}'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}