<?php

namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       $user = User::factory()->create([
            'imat'  => "1233330290",
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password')
        ]);

        $role = Role::create(['name' => 'super-admin']);
        $permission = Permission::create(['name' => 'all permissions']);

        $role->givePermissionTo([$permission->name]);

        $user->assignRole('super-admin');

    }
}
