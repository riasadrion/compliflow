<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceLog extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'client_id',
        'authorization_id',
        'user_id',
        'service_group_id',
        'curriculum_id',
        'service_code',
        'form_type',
        'service_date',
        'start_time',
        'end_time',
        'units',
        'report_status',
        'report_type',
        'ready_at',
        'submitted_at',
        'locked_at',
        'locked_by',
        'notes',
        'custom_fields',
        'last_billed_at',
    ];

    protected function casts(): array
    {
        return [
            'service_date'  => 'date',
            'ready_at'      => 'datetime',
            'submitted_at'  => 'datetime',
            'locked_at'     => 'datetime',
            'last_billed_at'=> 'datetime',
            'notes'         => 'encrypted',
            'custom_fields' => 'array',
        ];
    }

    // ── Status helpers ─────────────────────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->report_status === 'draft';
    }

    public function isReady(): bool
    {
        return $this->report_status === 'ready';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(Authorization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function serviceGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class);
    }

    public function generatedForms(): HasMany
    {
        return $this->hasMany(GeneratedForm::class);
    }
}
