<?php

namespace App\Services;

use App\Models\GeneratedForm;
use App\Models\ServiceLog;
use setasign\Fpdi\Fpdi;

class PdfOverlayService
{
    public function __construct(
        private readonly FieldMappingService       $mapper,
        private readonly CryptographicAuditService $audit,
        private readonly S3SecureStorageService    $s3,
        private readonly BillingService            $billing,
    ) {}

    public function generate(ServiceLog $log, string $type): string
    {
        $type = strtoupper($type);

        $existing = GeneratedForm::where('service_log_id', $log->id)
            ->where('form_type', $type)
            ->first();

        if ($existing && $existing->status === 'completed') {
            return $this->s3->presignedUrl($existing);
        }

        $values = $this->mapper->map($log, $type);
        $coords = $this->loadCoords($type);

        $templatePath = storage_path("app/templates/{$type}_template.pdf");
        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Template not found for {$type}.");
        }

        $filename   = "{$type}_{$log->id}_" . now()->format('Ymd_His') . '.pdf';
        $outputDir  = storage_path('app/generated');
        @mkdir($outputDir, 0755, true);
        $outputPath = $outputDir . '/' . $filename;

        $pdf = new Fpdi();
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $pageCount = $pdf->setSourceFile($templatePath);

        $mmPerPt = 25.4 / 72.0;

        for ($page = 1; $page <= $pageCount; $page++) {
            $tplId = $pdf->importPage($page);
            $size  = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            // FPDI returns size in mm by default. Convert back to pt to compare with rect coords.
            $pageHeightPt = $size['height'] / $mmPerPt;

            foreach ($coords as $name => $cfg) {
                if (($cfg['page'] ?? 1) !== $page) {
                    continue;
                }
                $value = $values[$name] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $this->drawField($pdf, $cfg, $value, $pageHeightPt);
            }
        }

        $pdf->Output($outputPath, 'F');

        $hash  = hash_file('sha256', $outputPath);
        $s3Key = $this->s3->uploadWithWorm($outputPath, $log->crp_id, $type);

        @unlink($outputPath);

        $form = $existing
            ? tap($existing)->update([
                'status'        => 'completed',
                'file_path'     => $s3Key,
                'pdf_hash'      => $hash,
                'error_message' => null,
            ])
            : GeneratedForm::create([
                'crp_id'         => $log->crp_id,
                'service_log_id' => $log->id,
                'form_type'      => $type,
                'status'         => 'completed',
                'file_path'      => $s3Key,
                'pdf_hash'       => $hash,
            ]);

        $this->audit->log(
            $log->crp_id,
            auth()->id(),
            'pdf_generated',
            ServiceLog::class,
            $log->id,
            ['form_type' => $type, 'pdf_hash' => $hash, 's3_key' => $s3Key],
        );

        $this->billing->deductForExport($log->fresh(), $form);

        return $this->s3->presignedUrl($form);
    }

    public function existingFor(ServiceLog $log, string $type): ?GeneratedForm
    {
        return GeneratedForm::where('service_log_id', $log->id)
            ->where('form_type', strtoupper($type))
            ->where('status', 'completed')
            ->first();
    }

    private function loadCoords(string $type): array
    {
        $path = storage_path("app/templates/{$type}_coords.json");
        if (! file_exists($path)) {
            throw new \RuntimeException("Coordinate map not found for {$type}.");
        }
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Draw a single field. PDF coordinates are bottom-left origin;
     * FPDI/FPDF use top-left origin, so y must be flipped against page height.
     */
    private function drawField(Fpdi $pdf, array $cfg, string $value, float $pageHeight): void
    {
        [$x1, $y1, $x2, $y2] = $cfg['rect'];
        $w = $x2 - $x1;
        $h = $y2 - $y1;

        // Convert PDF y (from bottom) to FPDF y (from top)
        $yTop = $pageHeight - $y2;

        if (($cfg['type'] ?? 'text') === 'button') {
            // Render checkmark only when value is "On" or "Yes" (truthy radio/checkbox state)
            $checked = in_array(strtolower($value), ['on', 'yes', '1', 'true'], true);
            if (! $checked) {
                return;
            }
            $this->drawCheckmark($pdf, $x1, $yTop, $w, $h);
            return;
        }

        // Text field
        $maxFontSize = $cfg['font_size'] ?? 12;
        $minFontSize = 7;
        $ptToMm = 25.4 / 72.0;
        $xMm = $x1 * $ptToMm;
        $yMm = $yTop * $ptToMm;
        $wMm = $w  * $ptToMm;
        $hMm = $h  * $ptToMm;

        $text = $this->sanitize($value);

        // Auto-shrink to fit width: try max size, step down 0.5 at a time until it fits.
        $padding = 2;       // mm of horizontal slack inside the cell
        $available = max(1, $wMm - $padding);
        $fontSize = $maxFontSize;
        $pdf->SetFont('Helvetica', '', $fontSize);
        while ($fontSize > $minFontSize && $pdf->GetStringWidth($text) > $available) {
            $fontSize -= 0.5;
            $pdf->SetFont('Helvetica', '', $fontSize);
        }

        // Cell with full rect height — FPDF vertically centers text within the cell.
        $pdf->SetXY($xMm + 1, $yMm);
        $pdf->Cell($wMm, $hMm, $text, 0, 0, 'L', false);
    }

    private function drawCheckmark(Fpdi $pdf, float $x, float $yTop, float $w, float $h): void
    {
        $ptToMm = 25.4 / 72.0;
        $xMm = $x * $ptToMm;
        $yMm = $yTop * $ptToMm;
        $wMm = $w * $ptToMm;
        $hMm = $h * $ptToMm;

        // Draw a checkmark using line segments (matches the original /Yes appearance style)
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.35);

        $p1x = $xMm + 0.111 * $wMm;
        $p1y = $yMm + 0.500 * $hMm;
        $p2x = $xMm + 0.400 * $wMm;
        $p2y = $yMm + 0.880 * $hMm;
        $p3x = $xMm + 0.889 * $wMm;
        $p3y = $yMm + 0.120 * $hMm;

        $pdf->Line($p1x, $p1y, $p2x, $p2y);
        $pdf->Line($p2x, $p2y, $p3x, $p3y);
    }

    private function sanitize(string $value): string
    {
        // FPDF uses Latin-1; strip anything else to avoid render errors
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }
}
