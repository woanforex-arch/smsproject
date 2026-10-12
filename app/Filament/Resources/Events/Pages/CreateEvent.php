<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}