<?php

namespace App\Services;

use App\Models\Curriculum;
use Illuminate\Support\Collection;

/**
 * Controls whether a curriculum can be used in a service log.
 *
 * A curriculum is usable if:
 *   - It belongs to the CRP (tenant isolation)
 *   - Status is 'approved'
 *   - expires_at is null or in the future
 */
class CurriculumBlockingService
{
    /**
     * Can this specific curriculum be used for a new service log?
     */
    public function canLogService(int $curriculumId, int $crpId): bool
    {
        $curriculum = Curriculum::where('id', $curriculumId)
            ->where('crp_id', $crpId)
            ->first();

        if (! $curriculum) return false;

        return $curriculum->isApproved();
    }

    /**
     * Returns all valid (approved, not expired) curricula for a CRP.
     * Used to populate dropdowns in the service log form.
     */
    public function getValidCurricula(int $crpId, ?string $serviceCode = null): Collection
    {
        return Curriculum::where('crp_id', $crpId)
            ->where('status', 'approved')
            ->where(fn ($q) =>
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now())
            )
            ->when($serviceCode, fn ($q) => $q->where('service_code', $serviceCode))
            ->orderBy('title')
            ->get();
    }
}
