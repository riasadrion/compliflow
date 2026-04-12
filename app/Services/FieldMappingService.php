<?php

namespace App\Services;

use App\Models\ServiceLog;

/**
 * Deterministic field mapping for 963X, 964X, and 122X forms.
 * Maps ServiceLog + related models → flat array ready for Blade/PDF.
 */
class FieldMappingService
{
    public function map(ServiceLog $log, string $type): array
    {
        $custom = $log->custom_fields ?? [];
        $client = $log->client;
        $auth   = $log->authorization;

        return match (strtoupper($type)) {

            '963X' => [
                'client_name'          => trim($client->first_name . ' ' . $client->last_name),
                'dob'                  => $client->dob,
                'service_date'         => $log->service_date?->format('m/d/Y'),
                'authorization_number' => $auth?->authorization_number,
                'hours'                => $log->units,
                'service_code'         => $log->service_code ?? $custom['service_code'] ?? null,
                'signature_present'    => ! empty($custom['signature_present']),
                'notes'                => $log->notes,
            ],

            '964X' => [
                'client_name'   => trim($client->first_name . ' ' . $client->last_name),
                'dob'           => $client->dob,
                'employer'      => $custom['employer'] ?? '',
                'wage_rate'     => $custom['wage_rate'] ?? 0,
                'hours_worked'  => $log->units,
                'pay_date'      => $custom['pay_date'] ?? null,
                'signature'     => ! empty($custom['signature_present']),
                'payroll_data'  => $custom['payroll_data'] ?? null,
            ],

            '122X' => [
                'client_name'  => trim($client->first_name . ' ' . $client->last_name),
                'dob'          => $client->dob,
                'service_date' => $log->service_date?->format('m/d/Y'),
                'service_code' => $log->service_code ?? $custom['service_code'] ?? null,
                'notes'        => $log->notes ?? '',
            ],

            default => []
        };
    }

    /**
     * Map all logs for a client to a given form type.
     */
    public function mapAll(int $clientId, string $type): array
    {
        return ServiceLog::where('client_id', $clientId)
            ->with(['client', 'authorization'])
            ->get()
            ->map(fn ($log) => $this->map($log, $type))
            ->toArray();
    }
}
