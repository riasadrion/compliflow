<?php

namespace App\Filament\Resources\CrpUserResource\Pages;

use App\Filament\Resources\CrpUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrpUsers extends ListRecords
{
    protected static string $resource = CrpUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
