<?php

namespace App\Filament\Resources\CrpResource\Pages;

use App\Filament\Resources\CrpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrps extends ListRecords
{
    protected static string $resource = CrpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
