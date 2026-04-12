<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CryptographicAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

class MfaController extends Controller
{
    public function __construct(
        private readonly CryptographicAuditService $auditService,
    ) {}

    /**
     * Generate a new TOTP secret and return the QR code URL.
     * User has not yet enabled MFA — this is the setup step.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        $secret = Google2FA::generateSecretKey();

        // Store encrypted — never plaintext in DB
        $user->update([
            'mfa_secret_encrypted' => Crypt::encryptString($secret),
        ]);

        $qrCodeUrl = Google2FA::getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json([
            'qr_code_url' => $qrCodeUrl,
            'secret'      => $secret, // Shown once for manual entry
        ]);
    }

    /**
     * Verify the 6-digit TOTP code and activate MFA.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $user   = $request->user();
        $secret = Crypt::decryptString($user->mfa_secret_encrypted);
        $code   = $request->input('code');

        // Prevent replay attacks — each code usable once
        if ($user->mfa_code_used_at && $user->mfa_code_used_at->gt(now()->subSeconds(30))) {
            return response()->json(['message' => 'Code already used. Please wait for the next code.'], 422);
        }

        $valid = Google2FA::verifyKey($secret, $code);

        if (! $valid) {
            $this->auditService->log($user->crp_id, $user->id, 'mfa_failed', 'user', $user->id, [
                'classification' => 'security',
            ]);

            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        // Mark MFA as enabled and record code use timestamp
        $user->update([
            'mfa_enabled'      => true,
            'mfa_verified_at'  => now(),
            'mfa_code_used_at' => now(),
        ]);

        // Set session flag — middleware checks this
        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $user->crp_id,
        ]);

        $this->auditService->log($user->crp_id, $user->id, 'mfa_verified', 'user', $user->id, [
            'classification' => 'security',
        ]);

        return response()->json(['message' => 'MFA verified successfully.']);
    }

    /**
     * Challenge — user is already authenticated but MFA session has expired.
     * They re-enter their current TOTP code.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $user   = $request->user();
        $secret = Crypt::decryptString($user->mfa_secret_encrypted);
        $code   = $request->input('code');

        if ($user->mfa_code_used_at && $user->mfa_code_used_at->gt(now()->subSeconds(30))) {
            return response()->json(['message' => 'Code already used. Please wait for the next code.'], 422);
        }

        $valid = Google2FA::verifyKey($secret, $code);

        if (! $valid) {
            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        $user->update(['mfa_code_used_at' => now()]);

        session([
            'mfa_verified_at'  => now()->timestamp,
            'mfa_verified_crp' => $user->crp_id,
        ]);

        return response()->json(['message' => 'MFA challenge passed.']);
    }
}
