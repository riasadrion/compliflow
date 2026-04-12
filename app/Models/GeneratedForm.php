<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneratedForm extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'service_log_id',
        'form_type',
        'status',
        'file_path',
        'pdf_hash',
        'error_message',
        'retry_count',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function serviceLog(): BelongsTo
    {
        return $this->belongsTo(ServiceLog::class);
    }
}
