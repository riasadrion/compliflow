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

        // Only log authenticated CRP users — not super admin, not unauthenticated
        if (! auth()->check()) return $response;
        $user = auth()->user();
        if ($user->isSuperAdmin() || ! $user->crp_id) return $response;

        // Only log PHI resource paths — skip navigation, widgets, assets
        $entityType = $this->resolveEntityType($request);
        if (! $entityType) return $response;

        // Only log successful responses
        if (! $response->isSuccessful()) return $response;

        // Skip Livewire polling (GET requests with X-Livewire header)
        if ($request->header('X-Livewire') && $request->method() === 'GET') {
            return $response;
        }

        $this->auditService->log(
            crpId:      $user->crp_id,
            userId:     $user->id,
            action:     $this->resolveAction($request),
            entityType: $entityType,
            entityId:   $this->resolveEntityId($request),
            metadata:   [
                'classification' => 'compliance',
                'method'         => $request->method(),
                'path'           => $request->path(),
            ],
        );

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

        if (str_contains($path, 'clients'))         return 'client';
        if (str_contains($path, 'service-logs'))    return 'service_log';
        if (str_contains($path, 'authorizations'))  return 'authorization';
        if (str_contains($path, 'curricula'))       return 'curriculum';
        if (str_contains($path, 'wble-payrolls'))   return 'wble_payroll';
        if (str_contains($path, 'wble-placements')) return 'wble_placement';
        if (str_contains($path, 'wble-employers'))  return 'wble_employer';
        if (str_contains($path, 'pdf-export'))      return 'pdf_export';
        if (str_contains($path, 'reports'))         return 'report';

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
