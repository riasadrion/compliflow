<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WblePlacement extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'client_id',
        'wble_employer_id',
        'job_title',
        'job_duties',
        'planned_start_date',
        'actual_start_date',
        'end_date',
        'district_notice_sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date'      => 'date',
            'actual_start_date'       => 'date',
            'end_date'                => 'date',
            'district_notice_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(WbleEmployer::class, 'wble_employer_id');
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(WblePayrollRecord::class);
    }
}
