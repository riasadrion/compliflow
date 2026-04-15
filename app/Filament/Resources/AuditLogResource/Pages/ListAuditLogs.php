<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Services\CryptographicAuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify_chain')
                ->label('Verify Chain Integrity')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->action(function () {
                    $crpId = auth()->user()->crp_id;
                    $valid = app(CryptographicAuditService::class)->verifyChain($crpId);

                    if ($valid) {
                        Notification::make()
                            ->title('Chain Intact')
                            ->body('All audit records verified. No tampering detected.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Chain Compromised')
                            ->body('Hash chain verification failed. One or more records may have been tampered with.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
