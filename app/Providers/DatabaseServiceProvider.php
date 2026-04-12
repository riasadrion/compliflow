<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Sets the PostgreSQL session variable app.current_crp_id before every query.
 *
 * This activates the RLS policies. Without this, RLS returns zero rows
 * (safe failure) because current_setting('app.current_crp_id', true) is null.
 *
 * Uses a guard flag to prevent recursive calls — the SET statement itself
 * would otherwise trigger beforeExecuting again.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    private bool $setting = false;

    public function boot(): void
    {
        DB::connection()->beforeExecuting(function () {
            if ($this->setting) {
                return;
            }

            $this->setting = true;

            try {
                $this->setCrpContext();
            } finally {
                $this->setting = false;
            }
        });
    }

    private function setCrpContext(): void
    {
        if (! Auth::check()) {
            DB::statement("SET LOCAL app.current_crp_id = ''");
            return;
        }

        $crpId = Auth::user()->crp_id;

        if ($crpId) {
            // PostgreSQL does not support parameter binding in SET statements
            DB::statement("SET LOCAL app.current_crp_id = " . (int) $crpId);
        }
    }
}
