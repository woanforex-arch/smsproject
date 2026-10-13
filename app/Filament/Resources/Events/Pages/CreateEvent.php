<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Services\EventImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Default the slug from the title and stamp the creating user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : Event::uniqueSlug($data['title']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(route('exports.events.template'))
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

            Action::make('import')
                ->label('Import from Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->modalHeading('Import events from a spreadsheet')
                ->modalDescription('Upload an Excel (.xlsx) or CSV file. The first row must name the columns — download the template for the exact layout.')
                ->modalSubmitActionLabel('Import events')
                ->modalWidth('lg')
                ->schema([
                    FileUpload::make('file')
                        ->label('Spreadsheet')
                        ->required()
                        ->disk('local')
                        ->directory('event-imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'application/csv',
                            'text/plain',
                        ])
                        ->maxSize(10240)
                        ->helperText(
                            'Columns: title, slug, description, venue, address, city, '
                            .'starts_at, ends_at, registration_deadline, status, capacity. '
                            .'Only title, starts_at and ends_at are required. '
                            .'Dates can be text (YYYY-MM-DD HH:MM) or native Excel date cells.'
                        ),
                ])
                ->action(function (array $data): void {
                    $this->importEvents($data['file']);
                }),
        ];
    }

    /** Import the uploaded spreadsheet and report the outcome. */
    protected function importEvents(string $storedPath): void
    {
        $disk = Storage::disk('local');

        try {
            $result = app(EventImportService::class)->import($disk->path($storedPath));
        } catch (Throwable $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } finally {
            // The upload has served its purpose whether or not parsing worked.
            $disk->delete($storedPath);
        }

        $notification = Notification::make()
            ->title("Imported {$result->created} ".Str::plural('event', $result->created));

        if ($result->hasErrors()) {
            $notification
                ->warning()
                ->body("Skipped {$result->skipped} row(s). {$result->errorSummary()}");
        } else {
            $notification
                ->success()
                ->body('Every row was imported successfully.');
        }

        $notification->send();

        // Send the admin to the list so they can review what was created.
        if ($result->created > 0) {
            $this->redirect(static::getResource()::getUrl('index'));
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
