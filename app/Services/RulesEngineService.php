<?php

namespace App\Services;

use App\Models\ServiceLog;

/**
 * Hard-coded rules engine.
 * Returns READY or BLOCKED with the first failing reason.
 * Maps to the 3 safety indicator flags in the dashboard.
 */
class RulesEngineService
{
    public function evaluate(int $clientId): array
    {
        $logs = ServiceLog::where('client_id', $clientId)->get();

        if ($logs->isEmpty()) {
            return $this->block('No service logs found');
        }

        foreach ($logs as $log) {
            $custom = $log->custom_fields ?? [];

            // Flag 1 — Missing Authorization
            if (! $log->authorization_id) {
                return $this->block('Missing Authorization', $log->id);
            }

            // Flag 2 — Missing Signature (963X and 964X require signature)
            if ($this->requiresSignature($log) && empty($custom['signature_present'])) {
                return $this->block('Missing Signature', $log->id);
            }

            // Flag 3 — Missing Payroll Data (964X / WBLE requires payroll)
            if ($this->isWble($log) && empty($custom['payroll_data'])) {
                return $this->block('Missing Payroll Data', $log->id);
            }

            // Service date must be present
            if (! $log->service_date) {
                return $this->block('Invalid Service Date', $log->id);
            }
        }

        return ['status' => 'READY', 'flags' => []];
    }

    /**
     * Evaluate all clients for a CRP and return a summary.
     */
    public function evaluateAll(int $crpId): array
    {
        $clientIds = ServiceLog::where('crp_id', $crpId)
            ->distinct()
            ->pluck('client_id');

        $results = [];

        foreach ($clientIds as $clientId) {
            $results[$clientId] = $this->evaluate($clientId);
        }

        return $results;
    }

    private function block(string $reason, ?int $logId = null): array
    {
        return [
            'status'     => 'BLOCKED',
            'reason'     => $reason,
            'log_id'     => $logId,
            'flags'      => [$reason],
        ];
    }

    private function requiresSignature(ServiceLog $log): bool
    {
        return in_array($log->form_type, ['963X', '964X']);
    }

    private function isWble(ServiceLog $log): bool
    {
        return $log->form_type === '964X';
    }
}
