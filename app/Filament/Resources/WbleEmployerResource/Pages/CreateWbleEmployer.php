<?php

namespace App\Filament\Resources\WbleEmployerResource\Pages;

use App\Filament\Resources\WbleEmployerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWbleEmployer extends CreateRecord
{
    protected static string $resource = WbleEmployerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['crp_id'] = auth()->user()->crp_id;
        return $data;
    }
}
