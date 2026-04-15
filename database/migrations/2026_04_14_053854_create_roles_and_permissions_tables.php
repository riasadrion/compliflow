<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Permissions — atomic capability flags (e.g. clients.create, clients.delete)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();        // e.g. "clients.create"
            $table->string('group');                 // e.g. "clients", "authorizations"
            $table->string('label');                 // Human-readable: "Create Clients"
            $table->timestamps();
        });

        // Roles — scoped per CRP (null crp_id = system default role)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                  // e.g. "admin", "counselor", "viewer"
            $table->string('label');                 // Human-readable: "Administrator"
            $table->boolean('is_system')->default(false); // System roles can't be deleted
            $table->timestamps();

            $table->unique(['crp_id', 'name']);
        });

        // Role <-> Permission pivot
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // User <-> Role pivot (a user has one role per CRP context)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        // Seed the 3 system roles with their permissions
        $this->seedSystemRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }

    private function seedSystemRoles(): void
    {
        $permissions = [
            // Clients
            ['name' => 'clients.view',   'group' => 'clients',        'label' => 'View Clients'],
            ['name' => 'clients.create', 'group' => 'clients',        'label' => 'Create Clients'],
            ['name' => 'clients.edit',   'group' => 'clients',        'label' => 'Edit Clients'],
            ['name' => 'clients.delete', 'group' => 'clients',        'label' => 'Delete Clients'],
            // Authorizations
            ['name' => 'authorizations.view',   'group' => 'authorizations', 'label' => 'View Authorizations'],
            ['name' => 'authorizations.create', 'group' => 'authorizations', 'label' => 'Create Authorizations'],
            ['name' => 'authorizations.edit',   'group' => 'authorizations', 'label' => 'Edit Authorizations'],
            ['name' => 'authorizations.delete', 'group' => 'authorizations', 'label' => 'Delete Authorizations'],
            // Audit Log
            ['name' => 'audit_log.view', 'group' => 'audit_log', 'label' => 'View Audit Log'],
            // Users
            ['name' => 'users.view',   'group' => 'users', 'label' => 'View Users'],
            ['name' => 'users.create', 'group' => 'users', 'label' => 'Create Users'],
            ['name' => 'users.edit',   'group' => 'users', 'label' => 'Edit Users'],
            ['name' => 'users.delete', 'group' => 'users', 'label' => 'Delete Users'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->insert(array_merge($p, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $allPermIds    = DB::table('permissions')->pluck('id', 'name');
        $clientPerms   = $allPermIds->filter(fn ($id, $name) => str_starts_with($name, 'clients.') || str_starts_with($name, 'authorizations.'));
        $viewOnlyPerms = $allPermIds->filter(fn ($id, $name) => str_ends_with($name, '.view'));

        $roles = [
            [
                'name'      => 'admin',
                'label'     => 'Administrator',
                'is_system' => true,
                'perms'     => $allPermIds->values()->all(), // all permissions
            ],
            [
                'name'      => 'counselor',
                'label'     => 'Counselor',
                'is_system' => true,
                'perms'     => $allPermIds
                    ->filter(fn ($id, $name) =>
                        (str_starts_with($name, 'clients.') || str_starts_with($name, 'authorizations.'))
                        && $name !== 'clients.delete'
                        && $name !== 'authorizations.delete'
                        || $name === 'audit_log.view'
                    )
                    ->values()->all(),
            ],
            [
                'name'      => 'viewer',
                'label'     => 'Viewer',
                'is_system' => true,
                'perms'     => $viewOnlyPerms->values()->all(),
            ],
        ];

        foreach ($roles as $role) {
            $roleId = DB::table('roles')->insertGetId([
                'crp_id'     => null,
                'name'       => $role['name'],
                'label'      => $role['label'],
                'is_system'  => $role['is_system'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($role['perms'] as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
};
