<?php

namespace App\Filament\Concerns;

/**
 * Role-based permission helpers for Filament resources.
 *
 * Checks permissions via the roles/permissions tables when a role_id is set.
 * Falls back to the legacy role string so existing data keeps working.
 *
 * Permission naming convention: "{resource}.{action}"
 * e.g. clients.view, clients.create, clients.edit, clients.delete
 */
trait EnforcesRoles
{
    protected static function userCan(string $permission): bool
    {
        $user = auth()->user();
        if (! $user || $user->isSuperAdmin()) return false;

        // Use permission table if role_id is set
        if ($user->role_id) {
            return \App\Models\Role::where('id', $user->role_id)
                ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                ->exists();
        }

        // Legacy fallback: derive from role string
        [$resource, $action] = explode('.', $permission, 2);
        return match ($user->role) {
            'admin'           => true,
            'senior_counselor'=> ! in_array($action, ['delete', 'lock', 'unlock', 'override', 'submit']),
            'counselor'       => in_array($action, ['view', 'create', 'edit', 'preview']),
            'readonly'        => $action === 'view',
            default           => false,
        };
    }

    protected static function resourcePermissionName(): string
    {
        return \Illuminate\Support\Str::plural(
            \Illuminate\Support\Str::snake(class_basename(static::$model ?? ''))
        );
    }

    public static function canCreate(): bool
    {
        return static::userCan(static::resourcePermissionName() . '.create');
    }

    public static function canEdit($record): bool
    {
        return static::userCan(static::resourcePermissionName() . '.edit');
    }

    public static function canDelete($record): bool
    {
        return static::userCan(static::resourcePermissionName() . '.delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan(static::resourcePermissionName() . '.delete');
    }

    public static function canView($record): bool
    {
        return static::userCan(static::resourcePermissionName() . '.view');
    }

    public static function canViewAny(): bool
    {
        return static::userCan(static::resourcePermissionName() . '.view');
    }
}
