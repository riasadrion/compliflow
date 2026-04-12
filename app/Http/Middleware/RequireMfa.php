<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to PHI routes unless the user has passed MFA this session.
 *
 * Applied to: /clients, /service-logs, /authorizations, /pdf-export, /reports
 *
 * Returns 403 (not redirect) on API requests, redirect to MFA challenge on web.
 */
class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // MFA not yet enabled — redirect to setup
        if (! $user->mfa_enabled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'MFA setup required before accessing PHI.',
                    'action'  => 'mfa_setup_required',
                ], 403);
            }

            return redirect()->route('mfa.setup');
        }

        // MFA enabled but not verified this session
        if (! $user->hasMfaVerifiedThisSession()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'MFA verification required.',
                    'action'  => 'mfa_challenge_required',
                ], 403);
            }

            return redirect()->route('mfa.challenge');
        }

        return $next($request);
    }
}
