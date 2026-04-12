<?php

namespace App\Observers;

use App\Models\Crp;
use App\Services\CryptographicAuditService;

class CrpObserver
{
    public function __construct(
        private readonly CryptographicAuditService $auditService,
    ) {}

    /**
     * Seed the genesis audit record whenever a new CRP is created.
     */
    public function created(Crp $crp): void
    {
        $this->auditService->seedGenesis($crp->id);
    }
}
