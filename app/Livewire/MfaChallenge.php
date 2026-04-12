<?php

namespace App\Livewire;

use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class MfaChallenge extends SimplePage
{
    protected string $view = 'livewire.mfa-challenge';

    public string $code = '';
    public ?string $errorMessage = null;

    public function verify(): void
    {
        $this->errorMessage = null;

        $user   = auth()->user();
        $secret = Crypt::decryptString($user->mfa_secret_encrypted);

        $valid = Google2FA::verifyKey($secret, $this->code);

        if (! $valid) {
            $this->dispatch('mfa-error', 'Invalid code. Please try again.');
            $this->code = '';
            return;
        }

        // Replay prevention
        if ($user->mfa_last_code === $this->code && $user->mfa_code_used_at && $user->mfa_code_used_at->gt(now()->subSeconds(30))) {
            $this->dispatch('mfa-error', 'Code already used. Please wait for the next code.');
            $this->code = '';
            return;
        }

        $user->update([
            'mfa_code_used_at' => now(),
            'mfa_last_code'    => $this->code,
        ]);

        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $user->crp_id,
        ]);

        $this->dispatch('mfa-success');
        $this->js("setTimeout(() => window.location.href = '/admin', 700)");
    }

public function getTitle(): string | Htmlable
    {
        return 'Two-Factor Verification';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Verification Required';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Enter the 6-digit code from your authenticator app to continue.';
    }
}
