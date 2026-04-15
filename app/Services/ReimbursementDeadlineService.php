<?php

namespace App\Services;

use App\Models\WblePayrollRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates and tracks reimbursement deadlines for WBLE payroll records.
 *
 * ACCESS-VR reimbursement must be submitted within 30 business days of pay date.
 */
class ReimbursementDeadlineService
{
    /**
     * Calculate the reimbursement deadline: 30 business days from pay date.
     */
    public function calculateDeadline(Carbon $payDate): Carbon
    {
        $deadline = $payDate->copy();
        $businessDays = 0;

        while ($businessDays < 30) {
            $deadline->addDay();
            if ($deadline->isWeekday()) {
                $businessDays++;
            }
        }

        return $deadline;
    }

    /**
     * Compute the deadline status based on today's date.
     *
     * on_track  — more than 10 business days remaining
     * warning   — 6–10 business days remaining
     * critical  — 1–5 business days remaining
     * overdue   — deadline has passed
     */
    public function computeStatus(Carbon $deadline): string
    {
        if ($deadline->isPast()) {
            return 'overdue';
        }

        $remaining = $this->businessDaysUntil($deadline);

        return match (true) {
            $remaining <= 5  => 'critical',
            $remaining <= 10 => 'warning',
            default          => 'on_track',
        };
    }

    /**
     * Pending records with deadline within 7 days (approaching).
     */
    public function getApproachingDeadlines(int $crpId): Collection
    {
        return WblePayrollRecord::where('crp_id', $crpId)
            ->where('reimbursement_status', 'pending')
            ->whereNotNull('reimbursement_deadline')
            ->where('reimbursement_deadline', '>', now())
            ->where('reimbursement_deadline', '<=', now()->addDays(7))
            ->orderBy('reimbursement_deadline')
            ->get();
    }

    /**
     * Pending records whose deadline has already passed.
     */
    public function getOverdueSubmissions(int $crpId): Collection
    {
        return WblePayrollRecord::where('crp_id', $crpId)
            ->where('reimbursement_status', 'pending')
            ->whereNotNull('reimbursement_deadline')
            ->where('reimbursement_deadline', '<', now())
            ->orderBy('reimbursement_deadline')
            ->get();
    }

    /**
     * Recalculate and persist deadline_status for a single record.
     */
    public function refreshStatus(WblePayrollRecord $record): void
    {
        if (! $record->reimbursement_deadline) return;

        $status = $this->computeStatus(Carbon::parse($record->reimbursement_deadline));
        $record->update(['deadline_status' => $status]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function businessDaysUntil(Carbon $date): int
    {
        $count = 0;
        $cursor = now()->copy();

        while ($cursor->lt($date)) {
            $cursor->addDay();
            if ($cursor->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }
}
