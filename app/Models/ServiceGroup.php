<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceGroup extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'authorization_id',
        'group_name',
        'session_date',
        'form_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(Authorization::class);
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }
}
