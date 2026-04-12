<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces tenant isolation at the Eloquent layer.
 *
 * Applied to all PHI models. Works alongside PostgreSQL RLS as a
 * defence-in-depth layer — RLS is the legal enforcement boundary,
 * this trait provides application-layer scoping.
 */
trait BelongsToCrp
{
    public static function bootBelongsToCrp(): void
    {
        // Auto-set crp_id on create from authenticated user
        static::creating(function (Model $model) {
            if (empty($model->crp_id) && auth()->check()) {
                $model->crp_id = auth()->user()->crp_id;
            }
        });

        // Global scope — all queries automatically filtered by tenant
        static::addGlobalScope('crp', function (Builder $builder) {
            $crpId = static::resolveCrpId();

            if ($crpId !== null) {
                $builder->where($builder->getModel()->getTable() . '.crp_id', $crpId);
            }
        });
    }

    /**
     * Resolves current CRP ID from auth user or explicit context.
     * Returns null only when no context is set (e.g., seeder scope bypass).
     */
    public static function resolveCrpId(): ?int
    {
        if (auth()->check()) {
            return auth()->user()->crp_id;
        }

        return null;
    }

    /**
     * Bypass the global CRP scope — use only in seeders or system jobs
     * where tenant context is set explicitly.
     */
    public static function withoutCrpScope(): Builder
    {
        return static::withoutGlobalScope('crp');
    }

    public function crp()
    {
        return $this->belongsTo(\App\Models\Crp::class);
    }
}
