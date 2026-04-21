<?php

namespace App\Services;

use App\Models\ServiceLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects service logs approaching or past the 10-day ACCESS-VR submission deadline.
 *
 * The 10-day clock starts from the service_date.
 * A log is considered approaching when <= 3 days remain.
 * A log is overdue when the deadline has passed and it is not yet submitted.
 */
class DeadlineAlertService
{
    private const DEADLINE_DAYS = 10;
    private const WARNING_THRESHOLD_DAYS = 3;

    /**
     * Calculate the submission deadline for a given service date.
     */
    public function getDeadline(Carbon $serviceDate): Carbon
    {
        return $serviceDate->copy()->addDays(self::DEADLINE_DAYS);
    }

    /**
     * Get the alert status for a single service log.
     * Returns: 'on_track' | 'warning' | 'overdue' | null (already submitted)
     */
    public function getStatus(ServiceLog $log): ?string
    {
        if ($log->report_status === 'submitted' || $log->report_status === 'approved') {
            return null;
        }

        $deadline = $this->getDeadline(Carbon::parse($log->service_date));
        $now = now();

        if ($now->gt($deadline)) {
            return 'overdue';
        }

        $daysRemaining = $now->diffInDays($deadline, false);

        if ($daysRemaining <= self::WARNING_THRESHOLD_DAYS) {
            return 'warning';
        }

        return 'on_track';
    }

    /**
     * Get days remaining until deadline (negative if overdue).
     */
    public function getDaysRemaining(ServiceLog $log): int
    {
        $deadline = $this->getDeadline(Carbon::parse($log->service_date));
        return (int) now()->diffInDays($deadline, false);
    }

    /**
     * Get all logs for a CRP that are approaching their deadline (within warning threshold).
     */
    public function getApproachingDeadlines(int $crpId): Collection
    {
        $warningDate = now()->subDays(self::DEADLINE_DAYS - self::WARNING_THRESHOLD_DAYS)->toDateString();
        $overdueDate = now()->subDays(self::DEADLINE_DAYS)->toDateString();

        return ServiceLog::where('crp_id', $crpId)
            ->whereNotIn('report_status', ['submitted', 'approved'])
            ->where('service_date', '>=', $overdueDate)
            ->where('service_date', '<=', $warningDate)
            ->with(['client', 'user'])
            ->orderBy('service_date')
            ->get();
    }

    /**
     * Get all overdue logs for a CRP (past 10 days, not submitted).
     */
    public function getOverdueLogs(int $crpId): Collection
    {
        $overdueDate = now()->subDays(self::DEADLINE_DAYS)->toDateString();

        return ServiceLog::where('crp_id', $crpId)
            ->whereNotIn('report_status', ['submitted', 'approved'])
            ->where('service_date', '<', $overdueDate)
            ->with(['client', 'user'])
            ->orderBy('service_date')
            ->get();
    }
}
