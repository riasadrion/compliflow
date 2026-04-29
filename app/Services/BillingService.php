<?php

namespace App\Services;

use App\Models\Authorization;
use App\Models\GeneratedForm;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\DB;

class BillingService
{
    private const DEDUCTING_FORMS = ['127X', '122X', '963X', '964X'];

    public function __construct(
        private readonly CryptographicAuditService $audit,
    ) {}

    public function shouldDeduct(string $formType): bool
    {
        return in_array(strtoupper($formType), self::DEDUCTING_FORMS, true);
    }

    public function deductForExport(ServiceLog $log, GeneratedForm $form): void
    {
        $type = strtoupper($form->form_type);

        if (! $this->shouldDeduct($type)) {
            return;
        }

        if ($log->last_billed_at !== null) {
            return;
        }

        $units = (int) ($log->units ?? 0);
        if ($units <= 0) {
            return;
        }

        DB::transaction(function () use ($log, $form, $type, $units) {
            $auth = Authorization::whereKey($log->authorization_id)->lockForUpdate()->first();

            if (! $auth) {
                throw new \RuntimeException("Authorization {$log->authorization_id} missing for service log {$log->id}");
            }

            $auth->increment('units_used', $units);
            $auth->update(['last_unit_used_at' => now()]);

            $log->forceFill([
                'last_billed_at' => now(),
            ])->save();

            $this->audit->log(
                $log->crp_id,
                auth()->id(),
                'units_deducted',
                ServiceLog::class,
                $log->id,
                [
                    'form_type'        => $type,
                    'units_deducted'   => $units,
                    'authorization_id' => $auth->id,
                    'units_used_after' => $auth->units_used,
                    'remaining'        => max(0, $auth->authorized_units - $auth->units_used),
                    'generated_form_id'=> $form->id,
                ],
            );
        });
    }
}
