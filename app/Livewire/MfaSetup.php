<?php

namespace App\Livewire;

use App\Services\CryptographicAuditService;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class MfaSetup extends Component
{
    public ?string $qrCodeUrl = null;
    public ?string $secret = null;
    public string $code = '';

    public function mount(): void
    {
        $user   = auth()->user();
        $secret = Google2FA::generateSecretKey();

        $user->update([
            'mfa_secret_encrypted' => Crypt::encryptString($secret),
        ]);

        $this->secret = $secret;

        $this->qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data='
            . urlencode(Google2FA::getQRCodeUrl(config('app.name'), $user->email, $secret));
    }

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
            'mfa_enabled'      => true,
            'mfa_verified_at'  => now(),
            'mfa_code_used_at' => now(),
            'mfa_last_code'    => $this->code,
        ]);

        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $user->crp_id,
        ]);

        app(CryptographicAuditService::class)->log($user->crp_id, $user->id, 'mfa_verified', 'user', $user->id, [
            'classification' => 'security',
        ]);

        $this->dispatch('mfa-success');
        $this->js("setTimeout(() => window.location.href = '/admin', 700)");
    }

    public function render()
    {
        return view('livewire.mfa-setup')
            ->layout('filament-panels::components.layout.simple');
    }
}
