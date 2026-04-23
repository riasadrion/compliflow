<?php

namespace App\Services;

use App\Models\Authorization;
use App\Models\Client;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imports clients, authorizations, and service logs from a CSV file.
 *
 * Expected CSV columns (all optional except client_id):
 *   client_id, first_name, last_name, dob,
 *   auth_number, auth_start, auth_end, service_code,
 *   service_date, start_time, end_time, hours, form_type,
 *   signature, payroll, wage_rate, pay_date, employer
 *
 * Tenant safety: every record is stamped with the authenticated user's crp_id.
 * On column-count mismatch the row is skipped and logged to errors.
 * On any other exception the whole import rolls back.
 */
class CsvImportService
{
    public function importFromPath(string $absolutePath): array
    {
        return $this->import($absolutePath);
    }

    public function import($file): array
    {
        $crpId  = Auth::user()->crp_id;
        $userId = Auth::id();

        $realPath = is_string($file) ? $file : $file->getRealPath();
        $lines    = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows   = array_map('str_getcsv', $lines);
        $header = array_shift($rows);
        $header = array_map('trim', $header);

        $imported        = 0;
        $skipped         = 0;
        $errors          = [];
        $clientsCreated  = 0;
        $clientsMatched  = 0;
        $authsCreated    = 0;
        $authsMatched    = 0;
        $logsCreated     = 0;

        DB::beginTransaction();

        try {
            // Set RLS context inside the transaction — SET LOCAL only persists within a transaction
            if (DB::getDriverName() === 'pgsql' && $crpId) {
                DB::statement("SET LOCAL app.current_crp_id = " . (int) $crpId);
            }

            foreach ($rows as $index => $row) {
                $rowNum       = $index + 2; // 1-based, header is row 1
                $rowCreatedAny = false;

                if (count($row) !== count($header)) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: expected " . count($header) . " columns, got " . count($row);
                    continue;
                }

                $data = array_combine($header, array_map('trim', $row));

                // Skip entirely blank rows
                if (empty(array_filter($data))) {
                    $skipped++;
                    continue;
                }

                // Validate minimum required fields
                if (empty($data['client_id'])) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: missing client_id — row skipped";
                    continue;
                }

                // Client — match on external_id + crp_id (includes trashed so we can revive rather than duplicate)
                $client = Client::withoutCrpScope()
                    ->withTrashed()
                    ->where('external_id', $data['client_id'])
                    ->where('crp_id', $crpId)
                    ->first();

                // If soft-deleted, restore it
                if ($client && $client->trashed()) {
                    $client->restore();
                }

                if (! $client) {
                    $client = Client::create([
                        'crp_id'             => $crpId,
                        'external_id'        => $data['client_id'],
                        'first_name'         => $data['first_name'] ?? '',
                        'last_name'          => $data['last_name'] ?? '',
                        'dob'                => $data['dob'] ?: null,
                        'eligibility_status' => 'pending',
                    ]);
                    $clientsCreated++;
                    $rowCreatedAny = true;
                } else {
                    $clientsMatched++;
                }

                // Authorization — match on auth_number + crp_id, create only if new
                $auth = null;
                if (! empty($data['auth_number'])) {
                    $auth = Authorization::withoutCrpScope()
                        ->withTrashed()
                        ->where('authorization_number', $data['auth_number'])
                        ->where('crp_id', $crpId)
                        ->first();

                    if ($auth && $auth->trashed()) {
                        $auth->restore();
                    }

                    if (! $auth) {
                        $auth = Authorization::create([
                            'crp_id'               => $crpId,
                            'client_id'            => $client->id,
                            'authorization_number' => $data['auth_number'],
                            'service_code'         => $data['service_code'] ?? '963X',
                            'service_type'         => $data['service_code'] ?? '963X',
                            'start_date'           => $data['auth_start'] ?: now()->toDateString(),
                            'end_date'             => $data['auth_end'] ?: now()->addYear()->toDateString(),
                            'authorized_units'     => $data['authorized_units'] ?? 100,
                            'units_used'           => 0,
                            'status'               => 'active',
                        ]);
                        $authsCreated++;
                        $rowCreatedAny = true;
                    } else {
                        $authsMatched++;
                    }
                }

                // Service Log — dedupe on client + date + start_time + service_code
                $logDuplicate = false;
                if (! empty($data['service_date']) && $auth) {
                    $serviceCode = $data['service_code'] ?? '963X';
                    $startTime   = $data['start_time'] ?? '09:00:00';

                    $existingLog = ServiceLog::withoutCrpScope()
                        ->where('crp_id', $crpId)
                        ->where('client_id', $client->id)
                        ->where('service_date', $data['service_date'])
                        ->where('start_time', $startTime)
                        ->where('service_code', $serviceCode)
                        ->first();

                    if ($existingLog) {
                        $logDuplicate = true;
                        $errors[] = "Row {$rowNum}: duplicate service log for {$data['client_id']} on {$data['service_date']} at {$startTime} — skipped";
                    } else {
                        ServiceLog::create([
                            'crp_id'           => $crpId,
                            'client_id'        => $client->id,
                            'authorization_id' => $auth->id,
                            'user_id'          => $userId,
                            'service_date'     => $data['service_date'],
                            'start_time'       => $startTime,
                            'end_time'         => $data['end_time'] ?? '10:00:00',
                            'units'            => $data['hours'] ?? 1,
                            'service_code'     => $serviceCode,
                            'form_type'        => $data['form_type'] ?? '963X',
                            'report_status'    => 'draft',
                            'custom_fields'    => array_filter([
                                'signature_present' => $data['signature'] ?? null,
                                'payroll_data'      => $data['payroll'] ?? null,
                                'wage_rate'         => $data['wage_rate'] ?? null,
                                'pay_date'          => $data['pay_date'] ?? null,
                                'employer'          => $data['employer'] ?? null,
                            ]),
                        ]);

                        $logsCreated++;
                        $rowCreatedAny = true;
                    }
                }

                if ($rowCreatedAny) {
                    $imported++;
                } else {
                    $skipped++;
                    if ($logDuplicate === false) {
                        $errors[] = "Row {$rowNum}: nothing new to import (all records already exist) — skipped";
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'status'           => 'complete',
            'imported'         => $imported,
            'skipped'          => $skipped,
            'errors'           => $errors,
            'clients_created'  => $clientsCreated,
            'clients_matched'  => $clientsMatched,
            'auths_created'    => $authsCreated,
            'auths_matched'    => $authsMatched,
            'logs_created'     => $logsCreated,
        ];
    }
}
