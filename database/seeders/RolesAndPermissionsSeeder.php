<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::query()->delete();
        Role::query()->delete();

        $permissions = [
            'view-clients',
            'create-clients',
            'edit-clients',
            'delete-clients',
            'view-devices',
            'create-devices',
            'edit-devices',
            'delete-devices',
            'view-financing-plans',
            'create-financing-plans',
            'edit-financing-plans',
            'delete-financing-plans',
            'view-phones',
            'create-phones',
            'edit-phones',
            'delete-phones',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'manage-roles',
            'view-dashboard',
            'view-sales-report',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo($permissions);

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(
            collect($permissions)->reject(fn ($p) => in_array($p, ['manage-roles', 'delete-users']))->values()->all()
        );

        $commercial = Role::create(['name' => 'commercial']);
        $commercial->givePermissionTo([
            'view-clients',
            'create-clients',
            'edit-clients',
            'view-financing-plans',
            'view-phones',
            'view-dashboard',
        ]);

        $gerant = Role::create(['name' => 'gerant']);
        $gerant->givePermissionTo([
            'view-clients',
            'create-clients',
            'view-devices',
            'view-financing-plans',
            'create-financing-plans',
            'view-phones',
            'create-phones',
            'view-dashboard',
            'view-sales-report',
        ]);

        $support = Role::create(['name' => 'support']);
        $support->givePermissionTo([
            'view-clients',
            'view-devices',
            'view-financing-plans',
            'view-dashboard',
        ]);
    }
}
