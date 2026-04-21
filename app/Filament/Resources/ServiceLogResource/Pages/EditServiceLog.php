<?php

namespace App\Filament\Resources\ServiceLogResource\Pages;

use App\Filament\Resources\ServiceLogResource;
use App\Services\CompletenessValidatorService;
use App\Services\CryptographicAuditService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceLog extends EditRecord
{
    protected static string $resource = ServiceLogResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            // Mark Ready — Senior Counselor and above
            Action::make('mark_ready')
                ->label('Mark as Ready')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () =>
                    $this->record->report_status === 'draft'
                    && ! $this->record->isLocked()
                    && in_array($user->role, ['admin', 'senior_counselor'])
                )
                ->action(function () use ($user) {
                    $result = app(CompletenessValidatorService::class)->validate($this->record);

                    if (! $result['valid']) {
                        Notification::make()
                            ->title('Cannot mark as ready — missing required fields')
                            ->body(implode("\n", $result['errors']))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    $this->record->update([
                        'report_status' => 'ready',
                        'ready_at'      => now(),
                    ]);

                    app(CryptographicAuditService::class)->log(
                        $user->crp_id, $user->id,
                        'service_log_marked_ready',
                        'service_log', $this->record->id,
                        ['classification' => 'compliance'],
                    );

                    Notification::make()->title('Marked as ready')->success()->send();
                    $this->refreshFormData(['report_status', 'ready_at']);
                }),

            // Lock — CRP Admin only
            Action::make('lock')
                ->label('Lock Record')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn () =>
                    ! $this->record->isLocked()
                    && $this->record->report_status === 'ready'
                    && $user->role === 'admin'
                )
                ->requiresConfirmation()
                ->modalDescription('Locking this record makes it immutable. Only a CRP Admin can unlock it.')
                ->action(function () use ($user) {
                    $this->record->update([
                        'locked_at' => now(),
                        'locked_by' => $user->id,
                    ]);

                    app(CryptographicAuditService::class)->log(
                        $user->crp_id, $user->id,
                        'service_log_locked',
                        'service_log', $this->record->id,
                        ['classification' => 'security'],
                    );

                    Notification::make()->title('Record locked')->success()->send();
                    $this->refreshFormData(['locked_at']);
                }),

            // Unlock — CRP Admin only
            Action::make('unlock')
                ->label('Unlock Record')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn () =>
                    $this->record->isLocked()
                    && $user->role === 'admin'
                )
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason for unlock')
                        ->required()
                        ->minLength(10),
                ])
                ->action(function (array $data) use ($user) {
                    $this->record->update([
                        'locked_at' => null,
                        'locked_by' => null,
                    ]);

                    app(CryptographicAuditService::class)->log(
                        $user->crp_id, $user->id,
                        'service_log_unlocked',
                        'service_log', $this->record->id,
                        ['classification' => 'security', 'reason' => $data['reason']],
                    );

                    Notification::make()->title('Record unlocked')->warning()->send();
                    $this->refreshFormData(['locked_at']);
                }),

            DeleteAction::make()
                ->visible(fn () => ! $this->record->isLocked() && $user->role === 'admin'),
        ];
    }

    public function isReadOnly(): bool
    {
        return $this->record->isLocked() && auth()->user()->role !== 'admin';
    }

    protected function afterSave(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id, $user->id,
            'service_log_updated',
            'service_log', $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
