<?php

namespace App\Models;

use App\Traits\BelongsToCrp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToCrp, HasFactory, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'external_id',
        'first_name',
        'last_name',
        'dob',
        'ssn_last_four',
        'address',
        'phone',
        'email',
        'eligibility_status',
        'proof_of_disability_received_at',
        'proof_of_disability_file_path',
        'iep_received_at',
        'iep_file_path',
        'consent_form_signed_at',
        'consent_form_file_path',
    ];

    // PHI encrypted at rest
    protected function casts(): array
    {
        return [
            'first_name'                      => 'encrypted',
            'last_name'                       => 'encrypted',
            'dob'                             => 'encrypted',
            'ssn_last_four'                   => 'encrypted',
            'proof_of_disability_received_at' => 'datetime',
            'iep_received_at'                 => 'datetime',
            'consent_form_signed_at'          => 'datetime',
        ];
    }

    // ── Document completeness ──────────────────────────────────────────────

    public function getMissingDocumentsAttribute(): array
    {
        $missing = [];

        if (! $this->proof_of_disability_received_at) {
            $missing[] = 'proof_of_disability';
        }
        if (! $this->iep_received_at) {
            $missing[] = 'iep';
        }
        if (! $this->consent_form_signed_at) {
            $missing[] = 'consent_form';
        }

        return $missing;
    }

    public function hasAllRequiredDocuments(): bool
    {
        return empty($this->missing_documents);
    }

    // ── Safe decrypt helper — only call when presenting to UI or PDF ───────

    public function safeDecrypt(string $field): ?string
    {
        return $this->{$field};
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function authorizations(): HasMany
    {
        return $this->hasMany(Authorization::class);
    }

    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    public function documentUploadLinks(): HasMany
    {
        return $this->hasMany(DocumentUploadLink::class);
    }
}
