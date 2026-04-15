<?php

namespace App\Filament\Resources\WblePlacementResource\Pages;

use App\Filament\Resources\WblePlacementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWblePlacement extends EditRecord
{
    protected static string $resource = WblePlacementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
