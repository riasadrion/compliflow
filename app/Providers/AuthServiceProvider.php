<?php

namespace App\Providers;

use App\Models\ServiceLog;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ── RBAC Gates ────────────────────────────────────────────────────

        // Export PDFs — admin and counselor only
        Gate::define('service_logs.export', function ($user) {
            return in_array($user->role, ['admin', 'counselor']);
        });

        // View PHI — all authenticated roles
        Gate::define('clients.view', function ($user) {
            return in_array($user->role, ['admin', 'counselor', 'viewer']);
        });

        // Create/edit clients — admin and counselor only
        Gate::define('clients.manage', function ($user) {
            return in_array($user->role, ['admin', 'counselor']);
        });

        // Admin-only actions
        Gate::define('admin.access', function ($user) {
            return $user->role === 'admin';
        });

        // Acknowledge authorization alerts
        Gate::define('authorization_alerts.acknowledge', function ($user) {
            return in_array($user->role, ['admin', 'counselor']);
        });
    }
}
