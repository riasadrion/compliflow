<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'crp_id',
        'name',
        'email',
        'password',
        'role',
        'mfa_secret_encrypted',
        'mfa_enabled',
        'mfa_verified_at',
        'mfa_code_used_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_verified_at'   => 'datetime',
            'mfa_code_used_at'  => 'datetime',
            'password'          => 'hashed',
            'mfa_enabled'       => 'boolean',
        ];
    }

    // ── Filament panel access ──────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->crp_id !== null;
    }

    // ── MFA helpers ────────────────────────────────────────────────────────

    public function hasMfaVerifiedThisSession(): bool
    {
        return session('mfa_verified_at') !== null
            && session('mfa_verified_crp') === $this->crp_id;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCounselor(): bool
    {
        return $this->role === 'counselor';
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function crp(): BelongsTo
    {
        return $this->belongsTo(Crp::class);
    }

    public function serviceLogs()
    {
        return $this->hasMany(ServiceLog::class);
    }
}
