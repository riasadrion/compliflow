<?php

namespace App\Filament\Resources\ServiceLogResource\Pages;

use App\Filament\Resources\ServiceLogResource;
use App\Services\CryptographicAuditService;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceLog extends ViewRecord
{
    protected static string $resource = ServiceLogResource::class;

    protected function afterMount(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id, $user->id,
            'service_log_viewed',
            'service_log', $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
