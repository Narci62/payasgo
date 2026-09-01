<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function super_admin_can_access_admin_panel(): void
    {
        $panelPerms = [
            'view-clients', 'view-devices', 'view-phones', 'view-financing-plans',
            'view-users', 'manage-roles', 'view-dashboard', 'view-sales-report',
        ];
        foreach ($panelPerms as $perm) {
            Permission::create(['name' => $perm]);
        }

        $user = User::factory()->create();
        $role = Role::create(['name' => 'super-admin']);
        $role->givePermissionTo($panelPerms);
        $user->assignRole('super-admin');

        $this->assertTrue($user->hasAnyPermission($panelPerms));
    }

    #[Test]
    public function it_can_create_a_role(): void
    {
        $role = Role::create(['name' => 'test-role']);

        $this->assertSame('test-role', $role->name);
        $this->assertDatabaseHas('roles', ['name' => 'test-role']);
    }

    #[Test]
    public function it_can_assign_permissions_to_a_role(): void
    {
        $permView = Permission::create(['name' => 'view-clients']);
        $permCreate = Permission::create(['name' => 'create-clients']);
        Permission::create(['name' => 'delete-clients']);
        $role = Role::create(['name' => 'commercial']);

        $role->givePermissionTo([$permView, $permCreate]);

        $this->assertTrue($role->hasPermissionTo('view-clients'));
        $this->assertTrue($role->hasPermissionTo('create-clients'));
        $this->assertFalse($role->hasPermissionTo('delete-clients'));
    }

    #[Test]
    public function it_can_edit_role_permissions(): void
    {
        $role = Role::create(['name' => 'support']);
        Permission::create(['name' => 'view-clients']);
        Permission::create(['name' => 'view-devices']);

        $role->givePermissionTo('view-clients');
        $this->assertTrue($role->hasPermissionTo('view-clients'));

        $role->syncPermissions(['view-clients', 'view-devices']);
        $this->assertTrue($role->hasPermissionTo('view-clients'));
        $this->assertTrue($role->hasPermissionTo('view-devices'));
    }

    #[Test]
    public function it_can_remove_permissions_from_a_role(): void
    {
        $role = Role::create(['name' => 'temp']);
        Permission::create(['name' => 'view-clients']);

        $role->givePermissionTo('view-clients');
        $this->assertTrue($role->hasPermissionTo('view-clients'));

        $role->revokePermissionTo('view-clients');
        $this->assertFalse($role->hasPermissionTo('view-clients'));
    }

    #[Test]
    public function it_can_delete_a_role(): void
    {
        $role = Role::create(['name' => 'to-delete']);
        $this->assertDatabaseHas('roles', ['name' => 'to-delete']);

        $role->delete();
        $this->assertDatabaseMissing('roles', ['name' => 'to-delete']);
    }

    #[Test]
    public function it_prevents_duplicate_role_names(): void
    {
        Role::create(['name' => 'admin']);

        $this->expectException(\Exception::class);
        Role::create(['name' => 'admin']);
    }

    #[Test]
    public function it_can_assign_role_to_user_and_check_permissions(): void
    {
        Permission::create(['name' => 'view-clients']);
        Permission::create(['name' => 'create-clients']);
        Permission::create(['name' => 'delete-users']);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'commercial']);
        $role->givePermissionTo(['view-clients', 'create-clients']);

        $user->assignRole('commercial');

        $this->assertTrue($user->hasRole('commercial'));
        $this->assertTrue($user->hasPermissionTo('view-clients'));
        $this->assertTrue($user->hasPermissionTo('create-clients'));
        $this->assertFalse($user->hasPermissionTo('delete-users'));
    }

    #[Test]
    public function it_can_change_user_role(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'commercial']);
        Role::create(['name' => 'admin']);
        Permission::create(['name' => 'manage-roles']);

        $user->assignRole('commercial');
        $this->assertTrue($user->hasRole('commercial'));

        $user->syncRoles(['admin']);
        $this->assertFalse($user->hasRole('commercial'));
        $this->assertTrue($user->hasRole('admin'));
    }

    #[Test]
    public function it_can_create_role_with_all_permissions(): void
    {
        $permissions = [
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-devices', 'create-devices', 'edit-devices', 'delete-devices',
            'view-financing-plans', 'create-financing-plans', 'edit-financing-plans', 'delete-financing-plans',
            'view-phones', 'create-phones', 'edit-phones', 'delete-phones',
            'view-users', 'create-users', 'edit-users', 'delete-users',
            'manage-roles', 'view-dashboard', 'view-sales-report',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm]);
        }

        $role = Role::create(['name' => 'super-admin']);
        $role->givePermissionTo($permissions);

        $this->assertCount(23, $role->permissions);
        foreach ($permissions as $perm) {
            $this->assertTrue($role->hasPermissionTo($perm));
        }
    }
}
