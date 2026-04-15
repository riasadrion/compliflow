<?php

namespace App\Filament\Resources\AuthorizationResource\Pages;

use App\Filament\Resources\AuthorizationResource;
use App\Services\CryptographicAuditService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAuthorization extends EditRecord
{
    protected static string $resource = AuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id,
            $user->id,
            'authorization_updated',
            'authorization',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
