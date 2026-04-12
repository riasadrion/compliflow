<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Curriculum extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'title',
        'description',
        'standards_alignment',
        'status',
        'approved_at',
        'expires_at',
        'approved_by',
        'service_code',
    ];

    protected function casts(): array
    {
        return [
            'standards_alignment' => 'array',
            'approved_at'         => 'datetime',
            'expires_at'          => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }
}
