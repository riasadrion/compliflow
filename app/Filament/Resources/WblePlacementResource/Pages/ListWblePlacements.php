<?php

namespace App\Filament\Resources\WblePlacementResource\Pages;

use App\Filament\Resources\WblePlacementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWblePlacements extends ListRecords
{
    protected static string $resource = WblePlacementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
