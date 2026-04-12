<?php

namespace App\Providers;

use App\Models\Crp;
use App\Observers\CrpObserver;
use App\Services\CryptographicAuditService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CryptographicAuditService::class);
    }

    public function boot(): void
    {
        Crp::observe(CrpObserver::class);
    }
}
