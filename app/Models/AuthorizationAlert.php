<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationAlert extends Model
{
    use BelongsToCrp;

    protected $fillable = [
        'crp_id',
        'authorization_id',
        'alert_type',
        'severity',
        'acknowledged',
        'acknowledged_at',
        'acknowledged_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged'    => 'boolean',
            'acknowledged_at' => 'datetime',
            'sent_at'         => 'datetime',
        ];
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(Authorization::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
