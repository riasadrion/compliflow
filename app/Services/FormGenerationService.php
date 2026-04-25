<?php

namespace App\Services;

use App\Models\GeneratedForm;
use App\Models\ServiceLog;
use mikehaertl\pdftk\Pdf;

class FormGenerationService
{
    public function __construct(
        private readonly FieldMappingService       $mapper,
        private readonly CryptographicAuditService $audit,
    ) {}

    /**
     * Fill a fillable PDF template, flatten, hash, and store.
     * Idempotent: returns existing PDF path if one is already completed.
     * Throws when the log is not eligible for generation.
     *
     * Requires: pdftk on server (apt install pdftk-java).
     * Requires: storage/app/templates/{TYPE}_template.pdf with AcroForm fields.
     */
    public function generate(ServiceLog $log, string $type): string
    {
        $type = strtoupper($type);

        $existing = GeneratedForm::where('service_log_id', $log->id)
            ->where('form_type', $type)
            ->first();

        if ($existing && $existing->status === 'completed') {
            if (! file_exists($existing->file_path)) {
                throw new \RuntimeException(
                    'PDF was generated but the file is missing. Contact an administrator.'
                );
            }

            return $existing->file_path;
        }

        $fields = $this->mapper->map($log, $type);

        $templatePath = storage_path("app/templates/{$type}_template.pdf");
        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template not found for {$type}.");
        }

        $filename   = "{$type}_{$log->id}_" . now()->format('Ymd_His') . '.pdf';
        $outputDir  = storage_path('app/generated');
        $outputPath = $outputDir . '/' . $filename;
        @mkdir($outputDir, 0755, true);

        $pdf    = new Pdf($templatePath);
        $result = $pdf->fillForm($fields)
            ->needAppearances()
            ->flatten()
            ->saveAs($outputPath);

        if ($result === false) {
            if ($existing) {
                $existing->update([
                    'status'        => 'failed',
                    'error_message' => $pdf->getError(),
                    'retry_count'   => ($existing->retry_count ?? 0) + 1,
                ]);
            }
            throw new \RuntimeException('PDF generation failed: ' . $pdf->getError());
        }

        $hash = hash_file('sha256', $outputPath);

        if ($existing) {
            $existing->update([
                'status'        => 'completed',
                'file_path'     => $outputPath,
                'pdf_hash'      => $hash,
                'error_message' => null,
            ]);
        } else {
            GeneratedForm::create([
                'crp_id'         => $log->crp_id,
                'service_log_id' => $log->id,
                'form_type'      => $type,
                'status'         => 'completed',
                'file_path'      => $outputPath,
                'pdf_hash'       => $hash,
            ]);
        }

        $this->audit->log(
            $log->crp_id,
            auth()->id(),
            'pdf_generated',
            ServiceLog::class,
            $log->id,
            ['form_type' => $type, 'pdf_hash' => $hash],
        );

        return $outputPath;
    }

    /**
     * Returns the completed PDF record for a log, or null.
     */
    public function existingFor(ServiceLog $log, string $type): ?GeneratedForm
    {
        return GeneratedForm::where('service_log_id', $log->id)
            ->where('form_type', strtoupper($type))
            ->where('status', 'completed')
            ->first();
    }
}
