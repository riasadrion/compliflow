<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            // WBLE Employers
            ['name' => 'wble_employers.view',   'group' => 'wble', 'label' => 'View WBLE Employers'],
            ['name' => 'wble_employers.create', 'group' => 'wble', 'label' => 'Create WBLE Employer'],
            ['name' => 'wble_employers.edit',   'group' => 'wble', 'label' => 'Edit WBLE Employer'],
            ['name' => 'wble_employers.delete', 'group' => 'wble', 'label' => 'Delete WBLE Employer'],
            // WBLE Placements
            ['name' => 'wble_placements.view',   'group' => 'wble', 'label' => 'View WBLE Placements'],
            ['name' => 'wble_placements.create', 'group' => 'wble', 'label' => 'Create WBLE Placement'],
            ['name' => 'wble_placements.edit',   'group' => 'wble', 'label' => 'Edit WBLE Placement'],
            ['name' => 'wble_placements.delete', 'group' => 'wble', 'label' => 'Delete WBLE Placement'],
            // WBLE Payroll Records
            ['name' => 'wble_payroll_records.view',   'group' => 'wble', 'label' => 'View WBLE Payroll Records'],
            ['name' => 'wble_payroll_records.create', 'group' => 'wble', 'label' => 'Create WBLE Payroll Record'],
            ['name' => 'wble_payroll_records.edit',   'group' => 'wble', 'label' => 'Edit WBLE Payroll Record'],
            ['name' => 'wble_payroll_records.delete', 'group' => 'wble', 'label' => 'Delete WBLE Payroll Record'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(['name' => $data['name']], $data);
        }

        $allNames = collect($permissions)->pluck('name');

        $employerAll    = Permission::whereIn('name', ['wble_employers.view', 'wble_employers.create', 'wble_employers.edit', 'wble_employers.delete'])->pluck('id');
        $placementAll   = Permission::whereIn('name', ['wble_placements.view', 'wble_placements.create', 'wble_placements.edit', 'wble_placements.delete'])->pluck('id');
        $payrollAll     = Permission::whereIn('name', ['wble_payroll_records.view', 'wble_payroll_records.create', 'wble_payroll_records.edit', 'wble_payroll_records.delete'])->pluck('id');

        $editOnly = Permission::whereIn('name', [
            'wble_employers.view', 'wble_employers.create', 'wble_employers.edit',
            'wble_placements.view', 'wble_placements.create', 'wble_placements.edit',
            'wble_payroll_records.view', 'wble_payroll_records.create', 'wble_payroll_records.edit',
        ])->pluck('id');

        $viewOnly = Permission::whereIn('name', [
            'wble_employers.view',
            'wble_placements.view',
            'wble_payroll_records.view',
        ])->pluck('id');

        $allIds = $employerAll->merge($placementAll)->merge($payrollAll);

        Role::where('name', 'admin')->first()?->permissions()->syncWithoutDetaching($allIds);
        Role::where('name', 'senior_counselor')->first()?->permissions()->syncWithoutDetaching($allIds);
        Role::where('name', 'counselor')->first()?->permissions()->syncWithoutDetaching($editOnly);
        Role::where('name', 'readonly')->first()?->permissions()->syncWithoutDetaching($viewOnly);
    }

    public function down(): void
    {
        Permission::where('group', 'wble')->delete();
    }
};
