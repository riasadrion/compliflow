<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrpAuditLog extends Model
{
    use BelongsToCrp;

    // Audit logs are immutable — no updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'crp_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'metadata',
        'classification',
        'previous_hash',
        'current_hash',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function crp(): BelongsTo
    {
        return $this->belongsTo(Crp::class);
    }
}
