<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ServiceLog;
use Illuminate\Support\Collection;

/**
 * Evaluates safety flags on service logs for a client or entire CRP.
 *
 * Three hard-coded flags:
 *   1. missing_authorization — log has no active authorization linked
 *   2. missing_signature     — 963X or 964X log has no signature_present in custom_fields
 *   3. missing_payroll       — 964X log has no payroll_data in custom_fields
 *
 * evaluate(clientId)  → per-client summary: READY or BLOCKED with full flag list
 * evaluateAll(crpId)  → batch results for all clients in a CRP
 * evaluateLogs(crpId) → per-log flag list keyed by log id (used for table display)
 */
class RulesEngineService
{
    public const STATUS_READY   = 'READY';
    public const STATUS_BLOCKED = 'BLOCKED';

    /**
     * Evaluate all unsubmitted service logs for a single client.
     *
     * @return array{status: string, flags: array}
     */
    public function evaluate(int $clientId): array
    {
        $logs = ServiceLog::where('client_id', $clientId)
            ->whereNotIn('report_status', ['submitted', 'approved'])
            ->with('authorization')
            ->get();

        $flags = [];
        foreach ($logs as $log) {
            $flags = array_merge($flags, $this->evaluateLog($log));
        }

        return [
            'status' => empty($flags) ? self::STATUS_READY : self::STATUS_BLOCKED,
            'flags'  => $flags,
        ];
    }

    /**
     * Batch evaluate all clients in a CRP.
     *
     * @return Collection<int, array{client_id: int, client_name: string, status: string, flags: array}>
     */
    public function evaluateAll(int $crpId): Collection
    {
        $clients = Client::where('crp_id', $crpId)->get();

        return $clients->map(function (Client $client) {
            $result = $this->evaluate($client->id);
            return [
                'client_id'   => $client->id,
                'client_name' => $client->last_name . ', ' . $client->first_name,
                'status'      => $result['status'],
                'flags'       => $result['flags'],
            ];
        })->sortBy('client_name')->values();
    }

    /**
     * Get per-log evaluation keyed by service_log id.
     * Used for table column display.
     *
     * @return array<int, array{status: string, flags: array}>
     */
    public function evaluateLogs(int $crpId): array
    {
        $logs = ServiceLog::where('crp_id', $crpId)
            ->whereNotIn('report_status', ['submitted', 'approved'])
            ->with('authorization')
            ->get();

        $results = [];
        foreach ($logs as $log) {
            $logFlags = $this->evaluateLog($log);
            $results[$log->id] = [
                'status' => empty($logFlags) ? self::STATUS_READY : self::STATUS_BLOCKED,
                'flags'  => $logFlags,
            ];
        }

        return $results;
    }

    /**
     * Evaluate a single log and return its flags.
     *
     * @return array<int, array{log_id: int, flag: string, message: string}>
     */
    private function evaluateLog(ServiceLog $log): array
    {
        $flags  = [];
        $custom = $log->custom_fields ?? [];

        // Flag 1 — Missing Authorization
        if (! $log->authorization_id || ! $log->authorization) {
            $flags[] = [
                'log_id'  => $log->id,
                'flag'    => 'missing_authorization',
                'message' => 'No authorization linked to this service log.',
            ];
        }

        // Flag 2 — Missing Signature (963X and 964X only)
        if (in_array($log->form_type, ['963X', '964X']) && empty($custom['signature_present'])) {
            $flags[] = [
                'log_id'  => $log->id,
                'flag'    => 'missing_signature',
                'message' => "Signature is required for {$log->form_type} forms.",
            ];
        }

        // Flag 3 — Missing Payroll (964X only)
        if ($log->form_type === '964X' && empty($custom['payroll_data'])) {
            $flags[] = [
                'log_id'  => $log->id,
                'flag'    => 'missing_payroll',
                'message' => 'Payroll data is required for 964X (WBLE) forms.',
            ];
        }

        return $flags;
    }
}
