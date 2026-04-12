<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WblePayrollRecord extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'client_id',
        'wble_employer_id',
        'wble_placement_id',
        'pay_period_start',
        'pay_period_end',
        'hours_worked',
        'wage_rate',
        'gross_wages',
        'reimbursement_amount',
        'pay_date',
        'reimbursement_deadline',
        'employer_signature_date',
        'deadline_status',
        'reimbursement_status',
        'submitted_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'pay_period_start'        => 'date',
            'pay_period_end'          => 'date',
            'pay_date'                => 'date',
            'reimbursement_deadline'  => 'date',
            'employer_signature_date' => 'datetime',
            'submitted_at'            => 'datetime',
            'paid_at'                 => 'datetime',
            'hours_worked'            => 'decimal:2',
            'wage_rate'               => 'decimal:2',
            'gross_wages'             => 'decimal:2',
            'reimbursement_amount'    => 'decimal:2',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->reimbursement_deadline
            && $this->reimbursement_deadline->isPast()
            && $this->reimbursement_status === 'pending';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(WbleEmployer::class, 'wble_employer_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(WblePlacement::class, 'wble_placement_id');
    }
}
