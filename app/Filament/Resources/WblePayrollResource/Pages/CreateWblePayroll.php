<?php

namespace App\Filament\Resources\WblePayrollResource\Pages;

use App\Filament\Resources\WblePayrollResource;
use App\Services\ReimbursementDeadlineService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateWblePayroll extends CreateRecord
{
    protected static string $resource = WblePayrollResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['crp_id'] = auth()->user()->crp_id;

        // Auto-calculate reimbursement deadline and initial status
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
