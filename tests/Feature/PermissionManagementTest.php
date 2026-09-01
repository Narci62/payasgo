<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    #[Test]
    public function it_can_create_a_permission(): void
    {
        $perm = Permission::create(['name' => 'view-clients']);

        $this->assertSame('view-clients', $perm->name);
        $this->assertDatabaseHas('permissions', ['name' => 'view-clients']);
    }

    #[Test]
    public function it_can_create_multiple_permissions(): void
    {
        $perms = collect(['view-clients', 'create-clients', 'edit-clients', 'delete-clients'])
            ->map(fn ($name) => Permission::create(['name' => $name]));

        $this->assertCount(4, $perms);
        $this->assertCount(4, Permission::all());
    }

    #[Test]
    public function it_prevents_duplicate_permission_names(): void
    {
        Permission::create(['name' => 'view-clients']);

        $this->expectException(\Exception::class);
        Permission::create(['name' => 'view-clients']);
    }

    #[Test]
    public function it_can_delete_a_permission(): void
    {
        $perm = Permission::create(['name' => 'temp-perm']);
        $this->assertDatabaseHas('permissions', ['name' => 'temp-perm']);

        $perm->delete();
        $this->assertDatabaseMissing('permissions', ['name' => 'temp-perm']);
    }

    #[Test]
    public function it_can_delete_permission_assigned_to_role(): void
    {
        $role = Role::create(['name' => 'test-role']);
        $perm = Permission::create(['name' => 'view-clients']);
        $role->givePermissionTo($perm);

        $this->assertTrue($role->hasPermissionTo('view-clients'));

        $perm->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertDatabaseMissing('permissions', ['name' => 'view-clients']);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    }

    #[Test]
    public function it_can_retrieve_all_permissions(): void
    {
        $names = [
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-devices', 'create-devices', 'edit-devices', 'delete-devices',
            'view-financing-plans', 'create-financing-plans', 'edit-financing-plans', 'delete-financing-plans',
            'view-phones', 'create-phones', 'edit-phones', 'delete-phones',
            'view-users', 'create-users', 'edit-users', 'delete-users',
            'manage-roles', 'view-dashboard', 'view-sales-report',
        ];

        foreach ($names as $name) {
            Permission::create(['name' => $name]);
        }

        $this->assertCount(23, Permission::all());
    }

    #[Test]
    public function permission_belongs_to_roles(): void
    {
        $perm = Permission::create(['name' => 'view-clients']);
        $roleA = Role::create(['name' => 'admin']);
        $roleB = Role::create(['name' => 'commercial']);

        $roleA->givePermissionTo($perm);
        $roleB->givePermissionTo($perm);

        $perm->refresh();
        $this->assertCount(2, $perm->roles);
    }

    #[Test]
    public function role_permissions_are_refreshed_after_cache_clear(): void
    {
        $role = Role::create(['name' => 'support']);
        Permission::create(['name' => 'view-dashboard']);
        $role->givePermissionTo('view-dashboard');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole('support');

        $this->assertTrue($user->hasPermissionTo('view-dashboard'));
    }
}
