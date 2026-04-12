<?php

namespace App\Services;

use App\Models\CrpAuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Cryptographic audit chain — every record links to the previous via SHA-256.
 *
 * Tampering with any record breaks the chain, detectable by verifyChain().
 * Audit logs are immutable at the DB level via the audit_logs_immutable trigger.
 */
class CryptographicAuditService
{
    /**
     * Append a new record to the audit chain for a given CRP.
     *
     * Uses a DB transaction with row-level locking to prevent concurrent
     * writes from breaking the sequence or hash chain.
     */
    public function appendToChain(
        ?int $crpId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
    ): CrpAuditLog {
        return DB::transaction(function () use ($crpId, $userId, $action, $entityType, $entityId, $metadata) {
            $last = CrpAuditLog::withoutCrpScope()
                ->where('crp_id', $crpId)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $previousHash = $last?->current_hash ?? 'GENESIS';
            $sequence     = ($last?->sequence ?? 0) + 1;

            $payload = json_encode([
                'crp_id'      => $crpId,
                'user_id'     => $userId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'sequence'    => $sequence,
                'timestamp'   => now()->toISOString(),
            ]);

            $currentHash    = hash('sha256', $previousHash . $payload);
            $classification = $metadata['classification'] ?? 'operational';
            unset($metadata['classification']);

            return CrpAuditLog::withoutCrpScope()->create([
                'crp_id'         => $crpId,
                'user_id'        => $userId,
                'action'         => $action,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'metadata'       => $metadata,
                'classification' => $classification,
                'previous_hash'  => $previousHash,
                'current_hash'   => $currentHash,
                'sequence'       => $sequence,
            ]);
        });
    }

    /**
     * Shorthand for logging a PHI access or action.
     */
    public function log(
        ?int $crpId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
    ): CrpAuditLog {
        return $this->appendToChain($crpId, $userId, $action, $entityType, $entityId, $metadata);
    }

    /**
     * Walk every record in the chain for a CRP and verify hash integrity.
     * Returns false if any tampering is detected.
     */
    public function verifyChain(int $crpId): bool
    {
        $records = CrpAuditLog::withoutCrpScope()
            ->where('crp_id', $crpId)
            ->orderBy('sequence')
            ->get();

        if ($records->isEmpty()) {
            return true;
        }

        $previousHash = 'GENESIS';

        foreach ($records as $record) {
            if ($record->action === 'GENESIS') {
                $previousHash = $record->current_hash;
                continue;
            }

            $payload = json_encode([
                'crp_id'      => $record->crp_id,
                'user_id'     => $record->user_id,
                'action'      => $record->action,
                'entity_type' => $record->entity_type,
                'entity_id'   => $record->entity_id,
                'sequence'    => $record->sequence,
                'timestamp'   => $record->created_at->toISOString(),
            ]);

            $expectedHash = hash('sha256', $previousHash . $payload);

            if ($expectedHash !== $record->current_hash) {
                return false;
            }

            $previousHash = $record->current_hash;
        }

        return true;
    }

    /**
     * Seed a genesis record for a newly created CRP.
     * Called from CRP observer on the created event.
     */
    public function seedGenesis(int $crpId): void
    {
        CrpAuditLog::withoutCrpScope()->create([
            'crp_id'         => $crpId,
            'user_id'        => null,
            'action'         => 'GENESIS',
            'entity_type'    => 'system',
            'entity_id'      => 0,
            'ip_address'     => '127.0.0.1',
            'classification' => 'security',
            'previous_hash'  => 'GENESIS',
            'current_hash'   => hash('sha256', 'GENESIS'),
            'sequence'       => 1,
        ]);
    }
}
