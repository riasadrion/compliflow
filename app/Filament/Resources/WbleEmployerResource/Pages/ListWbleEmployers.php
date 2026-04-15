<?php

namespace App\Filament\Resources\WbleEmployerResource\Pages;

use App\Filament\Resources\WbleEmployerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWbleEmployers extends ListRecords
{
    protected static string $resource = WbleEmployerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
