<?php

namespace App\Filament\Resources\WblePayrollResource\Pages;

use App\Filament\Resources\WblePayrollResource;
use App\Services\ReimbursementDeadlineService;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWblePayroll extends EditRecord
{
    protected static string $resource = WblePayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate deadline if pay_date changed
        if (! empty($data['pay_date'])) {
            $service  = app(ReimbursementDeadlineService::class);
            $payDate  = Carbon::parse($data['pay_date']);
            $deadline = $service->calculateDeadline($payDate);

            $data['reimbursement_deadline'] = $deadline->toDateString();
            $data['deadline_status']        = $service->computeStatus($deadline);
        }

        return $data;
    }
}
