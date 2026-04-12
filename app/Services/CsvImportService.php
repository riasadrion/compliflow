<?php

namespace App\Services;

use App\Models\Authorization;
use App\Models\Client;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
    public function import($file): array
    {
        $crpId = Auth::user()->crp_id;
        $rows  = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                if (count($row) !== count($header)) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": column count mismatch";
                    continue;
                }

                $data = array_combine($header, $row);

                // Client — match on external_id + crp_id (tenant-safe)
                $client = Client::updateOrCreate(
                    ['external_id' => $data['client_id'], 'crp_id' => $crpId],
                    [
                        'crp_id'     => $crpId,
                        'first_name' => $data['first_name'] ?? ($data['name'] ?? ''),
                        'last_name'  => $data['last_name'] ?? '',
                        'dob'        => $data['dob'] ?? null,
                    ]
                );

                // Authorization — match on authorization_number + crp_id
                $auth = Authorization::updateOrCreate(
                    ['authorization_number' => $data['auth_number'], 'crp_id' => $crpId],
                    [
                        'crp_id'     => $crpId,
                        'client_id'  => $client->id,
                        'start_date' => $data['auth_start'] ?? null,
                        'end_date'   => $data['auth_end'] ?? null,
                        'status'     => 'active',
                    ]
                );

                // Service Log
                ServiceLog::create([
                    'crp_id'           => $crpId,
                    'client_id'        => $client->id,
                    'authorization_id' => $auth->id,
                    'service_date'     => $data['service_date'] ?? null,
                    'units'            => $data['hours'] ?? 0,
                    'form_type'        => $data['form_type'] ?? null,
                    'report_status'    => 'draft',
                    'custom_fields'    => [
                        'signature_present' => $data['signature'] ?? 0,
                        'payroll_data'      => $data['payroll'] ?? null,
                        'wage_rate'         => $data['wage_rate'] ?? null,
                        'pay_date'          => $data['pay_date'] ?? null,
                        'employer'          => $data['employer'] ?? null,
                        'service_code'      => $data['service_code'] ?? null,
                    ],
                ]);

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'status'   => 'imported',
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }
}
