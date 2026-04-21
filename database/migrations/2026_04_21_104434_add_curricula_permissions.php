<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'curricula.view',   'group' => 'curricula', 'label' => 'View Curricula'],
            ['name' => 'curricula.create', 'group' => 'curricula', 'label' => 'Create Curriculum'],
            ['name' => 'curricula.edit',   'group' => 'curricula', 'label' => 'Edit Curriculum'],
            ['name' => 'curricula.delete', 'group' => 'curricula', 'label' => 'Delete Curriculum'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(['name' => $data['name']], $data);
        }

        $all      = Permission::whereIn('name', ['curricula.view', 'curricula.create', 'curricula.edit', 'curricula.delete'])->pluck('id');
        $editOnly = Permission::whereIn('name', ['curricula.view', 'curricula.create', 'curricula.edit'])->pluck('id');
        $viewOnly = Permission::where('name', 'curricula.view')->pluck('id');

        Role::where('name', 'admin')->first()?->permissions()->syncWithoutDetaching($all);
        Role::where('name', 'senior_counselor')->first()?->permissions()->syncWithoutDetaching($all);
        Role::where('name', 'counselor')->first()?->permissions()->syncWithoutDetaching($editOnly);
        Role::where('name', 'readonly')->first()?->permissions()->syncWithoutDetaching($viewOnly);
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'curricula.view', 'curricula.create', 'curricula.edit', 'curricula.delete',
        ])->delete();
    }
};
