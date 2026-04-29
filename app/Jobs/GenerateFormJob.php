<?php

namespace App\Jobs;

use App\Models\GeneratedForm;
use App\Models\ServiceLog;
use App\Services\PdfOverlayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFormJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $serviceLogId,
        public string $formType,
        public ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return "generate-form:{$this->serviceLogId}:" . strtoupper($this->formType);
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(PdfOverlayService $pdf): void
    {
        $log = ServiceLog::find($this->serviceLogId);
        if (! $log) {
            Log::warning('GenerateFormJob: service log not found', ['id' => $this->serviceLogId]);
            return;
        }

        GeneratedForm::updateOrCreate(
            ['service_log_id' => $log->id, 'form_type' => strtoupper($this->formType)],
            ['crp_id' => $log->crp_id, 'status' => 'processing', 'error_message' => null],
        );

        $pdf->generate($log, $this->formType);
    }

    public function failed(Throwable $e): void
    {
        GeneratedForm::where('service_log_id', $this->serviceLogId)
            ->where('form_type', strtoupper($this->formType))
            ->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'retry_count'   => \DB::raw('retry_count + 1'),
            ]);
    }
}
