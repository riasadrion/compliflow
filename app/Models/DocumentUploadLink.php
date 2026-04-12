<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentUploadLink extends Model
{
    use BelongsToCrp;

    protected $fillable = [
        'crp_id',
        'client_id',
        'token',
        'document_type',
        'status',
        'expires_at',
        'uploaded_at',
        'reminder_day1_sent_at',
        'reminder_day3_sent_at',
        'reminder_day7_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'             => 'datetime',
            'uploaded_at'            => 'datetime',
            'reminder_day1_sent_at'  => 'datetime',
            'reminder_day3_sent_at'  => 'datetime',
            'reminder_day7_sent_at'  => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUploaded(): bool
    {
        return $this->status === 'uploaded';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
