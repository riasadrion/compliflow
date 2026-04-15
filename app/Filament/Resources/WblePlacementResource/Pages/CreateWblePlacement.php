<?php

namespace App\Filament\Resources\WblePlacementResource\Pages;

use App\Filament\Resources\WblePlacementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWblePlacement extends CreateRecord
{
    protected static string $resource = WblePlacementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['crp_id'] = auth()->user()->crp_id;
        return $data;
    }
}
