<?php

namespace App\Filament\Resources\CrpUserResource\Pages;

use App\Filament\Resources\CrpUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrpUser extends CreateRecord
{
    protected static string $resource = CrpUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-stamp the CRP of the creating admin
        $data['crp_id'] = auth()->user()->crp_id;
        return $data;
    }
}
