<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Services\CryptographicAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['crp_id'] = auth()->user()->crp_id;
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id,
            $user->id,
            'client_created',
            'client',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
