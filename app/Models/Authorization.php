<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Authorization extends Model
{
    use BelongsToCrp, HasFactory, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'client_id',
        'authorization_number',
        'service_code',
        'service_type',
        'start_date',
        'end_date',
        'authorized_units',
        'units_used',
        'vrc_name',
        'vrc_email',
        'district_office',
        'status',
        'last_unit_used_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'date',
            'end_date'          => 'date',
            'last_unit_used_at' => 'datetime',
        ];
    }

    // ── Computed attributes ────────────────────────────────────────────────

    public function getUnitsRemainingAttribute(): int
    {
        return max(0, $this->authorized_units - $this->units_used);
    }

    public function getUnitsPercentUsedAttribute(): float
    {
        if ($this->authorized_units === 0) {
            return 100.0;
        }

        return round(($this->units_used / $this->authorized_units) * 100, 1);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_date->lte(now())
            && $this->end_date->gte(now());
    }

    public function hasUnitsAvailable(int $units = 1): bool
    {
        return $this->units_remaining >= $units;
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AuthorizationAlert::class);
    }
}
