<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;
}