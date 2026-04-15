<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Services\CryptographicAuditService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

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
            'client_viewed',
            'client',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
