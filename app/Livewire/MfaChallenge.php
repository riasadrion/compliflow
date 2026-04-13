<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Crypt;
use Livewire\Component;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class MfaChallenge extends Component
{
    public string $code = '';

    public function verify(): void
    {
        $user   = auth()->user();
        $secret = Crypt::decryptString($user->mfa_secret_encrypted);

        $valid = Google2FA::verifyKey($secret, $this->code);

        if (! $valid) {
            $this->dispatch('mfa-error', 'Invalid code. Please try again.');
            $this->code = '';
            return;
        }

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

    public function render()
    {
        return view('livewire.mfa-challenge')
            ->layout('filament-panels::components.layout.simple');
    }
}
