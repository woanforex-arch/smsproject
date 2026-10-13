<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear')
                ->label('Clear log')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear the activity log?')
                ->modalDescription('Every recorded activity will be permanently deleted. This cannot be undone.')
                ->successNotificationTitle('Activity log cleared.')
                ->action(fn () => ActivityLog::query()->delete()),
        ];
    }
}
