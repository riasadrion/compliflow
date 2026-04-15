<?php

namespace App\Filament\Resources\AuthorizationResource\Pages;

use App\Filament\Resources\AuthorizationResource;
use App\Services\CryptographicAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthorization extends CreateRecord
{
    protected static string $resource = AuthorizationResource::class;

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
            'authorization_created',
            'authorization',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
