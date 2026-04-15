<?php

namespace App\Filament\Resources\AuthorizationResource\Pages;

use App\Filament\Resources\AuthorizationResource;
use App\Services\CryptographicAuditService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAuthorization extends ViewRecord
{
    protected static string $resource = AuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function afterView(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id,
            $user->id,
            'authorization_viewed',
            'authorization',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
