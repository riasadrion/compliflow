<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new permissions
        $newPermissions = [
            // Service logs
            ['name' => 'service_logs.view',   'group' => 'service_logs', 'label' => 'View Service Logs'],
            ['name' => 'service_logs.create', 'group' => 'service_logs', 'label' => 'Create Service Logs'],
            ['name' => 'service_logs.edit',   'group' => 'service_logs', 'label' => 'Edit Service Logs'],
            ['name' => 'service_logs.delete', 'group' => 'service_logs', 'label' => 'Delete Service Logs'],
            ['name' => 'service_logs.mark_ready', 'group' => 'service_logs', 'label' => 'Mark Logs as Ready'],
            // Locking
            ['name' => 'records.lock',   'group' => 'records', 'label' => 'Lock Records'],
            ['name' => 'records.unlock', 'group' => 'records', 'label' => 'Unlock Records'],
            ['name' => 'records.request_unlock', 'group' => 'records', 'label' => 'Request Record Unlock'],
            // Exports
            ['name' => 'exports.preview', 'group' => 'exports', 'label' => 'Preview PDF Export'],
            ['name' => 'exports.submit',  'group' => 'exports', 'label' => 'Submit/Finalize PDF Export'],
            // Overrides
            ['name' => 'records.override', 'group' => 'records', 'label' => 'Override Records (with reason)'],
            // Read-only access grants
            ['name' => 'users.grant_readonly', 'group' => 'users', 'label' => 'Grant Read-Only Access'],
        ];

        foreach ($newPermissions as $p) {
            DB::table('permissions')->insertOrIgnore(array_merge($p, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $p = DB::table('permissions')->pluck('id', 'name');

        // Rename 'admin' role label to 'CRP Admin'
        DB::table('roles')->where('name', 'admin')->update(['label' => 'CRP Admin']);

        // Rename 'viewer' role label to 'Read Only'
        DB::table('roles')->where('name', 'viewer')->update(['name' => 'readonly', 'label' => 'Read Only']);

        // Add Senior Counselor system role
        $seniorId = DB::table('roles')->insertGetId([
            'crp_id'     => null,
            'name'       => 'senior_counselor',
            'label'      => 'Senior Counselor',
            'is_system'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update CRP Admin — add all new permissions
        $adminId = DB::table('roles')->where('name', 'admin')->value('id');
        $adminNewPerms = [
            'service_logs.view', 'service_logs.create', 'service_logs.edit',
            'service_logs.delete', 'service_logs.mark_ready',
            'records.lock', 'records.unlock', 'records.override',
            'exports.preview', 'exports.submit',
            'users.grant_readonly',
        ];
        foreach ($adminNewPerms as $perm) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'       => $adminId,
                'permission_id' => $p[$perm],
            ]);
        }

        // Update Counselor — add service log + preview, no lock/export/override
        $counselorId = DB::table('roles')->where('name', 'counselor')->value('id');
        $counselorNewPerms = [
            'service_logs.view', 'service_logs.create', 'service_logs.edit',
            'exports.preview',
        ];
        foreach ($counselorNewPerms as $perm) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'       => $counselorId,
                'permission_id' => $p[$perm],
            ]);
        }

        // Senior Counselor — everything counselor has + mark_ready + request_unlock
        $counselorPerms = DB::table('role_permissions')
            ->where('role_id', $counselorId)
            ->pluck('permission_id');

        foreach ($counselorPerms as $permId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'       => $seniorId,
                'permission_id' => $permId,
            ]);
        }
        $seniorExtra = ['service_logs.mark_ready', 'records.request_unlock'];
        foreach ($seniorExtra as $perm) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'       => $seniorId,
                'permission_id' => $p[$perm],
            ]);
        }

        // Read Only — view permissions only
        $readonlyId = DB::table('roles')->where('name', 'readonly')->value('id');
        $viewPerms = DB::table('permissions')->where('name', 'like', '%.view')->pluck('id');
        // Clear existing and re-seed cleanly
        DB::table('role_permissions')->where('role_id', $readonlyId)->delete();
        foreach ($viewPerms as $permId) {
            DB::table('role_permissions')->insert([
                'role_id'       => $readonlyId,
                'permission_id' => $permId,
            ]);
        }
    }

    public function down(): void
    {
        // Remove senior_counselor role
        $seniorId = DB::table('roles')->where('name', 'senior_counselor')->value('id');
        if ($seniorId) {
            DB::table('role_permissions')->where('role_id', $seniorId)->delete();
            DB::table('roles')->where('id', $seniorId)->delete();
        }

        // Revert role renames
        DB::table('roles')->where('name', 'admin')->update(['label' => 'Administrator']);
        DB::table('roles')->where('name', 'readonly')->update(['name' => 'viewer', 'label' => 'Viewer']);

        // Remove new permissions
        DB::table('permissions')->whereIn('name', [
            'service_logs.view', 'service_logs.create', 'service_logs.edit',
            'service_logs.delete', 'service_logs.mark_ready',
            'records.lock', 'records.unlock', 'records.request_unlock', 'records.override',
            'exports.preview', 'exports.submit', 'users.grant_readonly',
        ])->delete();
    }
};
