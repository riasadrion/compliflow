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
    public function import($file): array
    {
        $crpId  = Auth::user()->crp_id;
        $userId = Auth::id();

        $lines  = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows   = array_map('str_getcsv', $lines);
        $header = array_shift($rows);
        $header = array_map('trim', $header);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // 1-based, header is row 1

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

                // Client — upsert on external_id + crp_id
                $client = Client::updateOrCreate(
                    ['external_id' => $data['client_id'], 'crp_id' => $crpId],
                    [
                        'crp_id'             => $crpId,
                        'first_name'         => $data['first_name'] ?? '',
                        'last_name'          => $data['last_name'] ?? '',
                        'dob'                => $data['dob'] ?: null,
                        'eligibility_status' => 'pending',
                    ]
                );

                // Authorization — upsert on auth_number + crp_id (if provided)
                $auth = null;
                if (! empty($data['auth_number'])) {
                    $auth = Authorization::updateOrCreate(
                        ['authorization_number' => $data['auth_number'], 'crp_id' => $crpId],
                        [
                            'crp_id'            => $crpId,
                            'client_id'         => $client->id,
                            'service_code'      => $data['service_code'] ?? '963X',
                            'service_type'      => $data['service_code'] ?? '963X',
                            'start_date'        => $data['auth_start'] ?: now()->toDateString(),
                            'end_date'          => $data['auth_end'] ?: now()->addYear()->toDateString(),
                            'authorized_units'  => $data['authorized_units'] ?? 100,
                            'units_used'        => 0,
                            'status'            => 'active',
                        ]
                    );
                }

                // Service Log — only create if service_date is present
                if (! empty($data['service_date']) && $auth) {
                    ServiceLog::create([
                        'crp_id'           => $crpId,
                        'client_id'        => $client->id,
                        'authorization_id' => $auth->id,
                        'user_id'          => $userId,
                        'service_date'     => $data['service_date'],
                        'start_time'       => $data['start_time'] ?? '09:00:00',
                        'end_time'         => $data['end_time'] ?? '10:00:00',
                        'units'            => $data['hours'] ?? 1,
                        'service_code'     => $data['service_code'] ?? '963X',
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
                }

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'status'   => 'complete',
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }
}
