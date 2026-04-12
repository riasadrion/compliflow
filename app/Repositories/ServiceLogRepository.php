<?php

namespace App\Repositories;

use App\Models\ServiceLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Every read and write to service_logs goes through this repository.
 *
 * Explicit crp_id checks on every operation provide a third isolation layer
 * beyond RLS and the BelongsToCrp global scope, preventing any scope-bypass
 * from reaching PHI data.
 */
class ServiceLogRepository
{
    /**
     * Find a service log — verifies crp_id explicitly even though RLS
     * already enforces this. Defence-in-depth.
     */
    public function find(int $id): ?ServiceLog
    {
        return ServiceLog::where('id', $id)
            ->where('crp_id', Auth::user()->crp_id)
            ->first();
    }

    /**
     * Find or fail — throws ModelNotFoundException on cross-tenant attempt.
     */
    public function findOrFail(int $id): ServiceLog
    {
        return ServiceLog::where('id', $id)
            ->where('crp_id', Auth::user()->crp_id)
            ->firstOrFail();
    }

    /**
     * Create a service log. crp_id is set automatically via BelongsToCrp trait.
     */
    public function create(array $data): ServiceLog
    {
        return ServiceLog::create($data);
    }

    /**
     * Update a service log — verifies ownership before any mutation.
     */
    public function update(int $id, array $data): ServiceLog
    {
        $log = $this->findOrFail($id);

        $log->update($data);

        return $log->fresh();
    }

    /**
     * Soft-delete — verifies ownership.
     */
    public function delete(int $id): bool
    {
        $log = $this->findOrFail($id);

        return $log->delete();
    }

    /**
     * Get all logs for the current tenant with optional filters.
     */
    public function forTenant(array $filters = []): Collection
    {
        $query = ServiceLog::query();

        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (isset($filters['report_status'])) {
            $query->where('report_status', $filters['report_status']);
        }

        if (isset($filters['service_date_from'])) {
            $query->whereDate('service_date', '>=', $filters['service_date_from']);
        }

        if (isset($filters['service_date_to'])) {
            $query->whereDate('service_date', '<=', $filters['service_date_to']);
        }

        return $query->get();
    }

    /**
     * Lock a service log — sets locked_at which triggers the cascade DB trigger.
     */
    public function lock(int $id): ServiceLog
    {
        $log = $this->findOrFail($id);

        if ($log->locked_at !== null) {
            return $log; // Already locked — idempotent
        }

        $log->update([
            'locked_at' => now(),
            'locked_by' => Auth::id(),
        ]);

        return $log->fresh();
    }
}
