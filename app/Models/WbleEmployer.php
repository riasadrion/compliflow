<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WbleEmployer extends Model
{
    use BelongsToCrp, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'employer_name',
        'employer_address',
        'contact_name',
        'contact_phone',
        'contact_email',
        'ein',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(WblePlacement::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(WblePayrollRecord::class);
    }
}
