<?php

namespace App\Http\Middleware;

use App\Services\CryptographicAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-logs every request to PHI routes to the cryptographic audit chain.
 *
 * Applied to: /clients, /service-logs, /authorizations, /pdf-export, /reports
 */
class AuditPhiAccess
{
    public function __construct(
        private readonly CryptographicAuditService $auditService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful responses (2xx) — failed auth is logged separately
        if ($response->isSuccessful() && auth()->check()) {
            $user = auth()->user();

            $this->auditService->log(
                crpId:      $user->crp_id,
                userId:     $user->id,
                action:     $this->resolveAction($request),
                entityType: $this->resolveEntityType($request),
                entityId:   $this->resolveEntityId($request),
                metadata:   [
                    'classification' => 'compliance',
                    'method'         => $request->method(),
                    'path'           => $request->path(),
                ],
            );
        }

        return $response;
    }

    private function resolveAction(Request $request): string
    {
        return match ($request->method()) {
            'GET'    => 'read',
            'POST'   => 'create',
            'PUT',
            'PATCH'  => 'update',
            'DELETE' => 'delete',
            default  => 'access',
        };
    }

    private function resolveEntityType(Request $request): ?string
    {
        $path = $request->path();

        if (str_contains($path, 'clients'))       return 'client';
        if (str_contains($path, 'service-logs'))  return 'service_log';
        if (str_contains($path, 'authorizations'))return 'authorization';
        if (str_contains($path, 'pdf-export'))    return 'pdf_export';
        if (str_contains($path, 'reports'))       return 'report';

        return null;
    }

    private function resolveEntityId(Request $request): ?int
    {
        $id = $request->route('id')
            ?? $request->route('serviceLog')
            ?? $request->route('client')
            ?? $request->route('authorization');

        return $id ? (int) $id : null;
    }
}
