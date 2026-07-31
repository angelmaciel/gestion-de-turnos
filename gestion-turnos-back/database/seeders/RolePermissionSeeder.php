<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage users',
            'manage specialties',
            'manage rooms',
            'manage professionals',
            'manage appointments',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        $roles = ['admin', 'mesa de entrada', 'preconsulta', 'profesional'];

        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        $adminRoleWeb = Role::findByName('admin', 'web');
        $adminRoleSanctum = Role::findByName('admin', 'sanctum');
        $adminRoleWeb->givePermissionTo($permissions);
        $adminRoleSanctum->givePermissionTo($permissions);
    }
}
