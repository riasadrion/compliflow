<?php

namespace App\Services;

use App\Models\ServiceLog;
use Illuminate\Support\Facades\Auth;

class FormGenerationService
{
    public function __construct(
        private readonly FieldMappingService $mapper,
    ) {}

    /**
     * Generate a PDF for all service logs belonging to a client.
     * Returns a Symfony StreamedResponse (PDF download).
     */
    public function generate(string $type, int $clientId): mixed
    {
        $type = strtoupper($type);

        $logs = ServiceLog::where('client_id', $clientId)
            ->with(['client', 'authorization'])
            ->get();

        $data = $logs->map(fn ($log) => $this->mapper->map($log, $type))->toArray();

        $viewKey = strtolower(str_replace('x', 'x', $type)); // 963x, 964x, 122x

        $html = view("forms.{$viewKey}", ['data' => $data])->render();

        return $this->renderPdf($html);
    }

    private function renderPdf(string $html): mixed
    {
        // mikehaertl/php-pdftk is for existing PDFs.
        // For HTML → PDF we use Laravel Dompdf (barryvdh/laravel-dompdf).
        // This will be wired up in Week 4 when PDF package is installed.
        // For now return the rendered HTML for preview.
        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
