<?php

namespace App\Filament\Resources\ServiceLogResource\Pages;

use App\Filament\Resources\ServiceLogResource;
use App\Services\CryptographicAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceLog extends CreateRecord
{
    protected static string $resource = ServiceLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['crp_id']  = $user->crp_id;
        $data['user_id'] = $user->id;
        $data['report_status'] = 'draft';
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id,
            $user->id,
            'service_log_created',
            'service_log',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
