<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'imat' => '123330290',
            'name' => 's admin',
            'email' => 'admin@trueline-system.com',
            'password' => Hash::make('admin.trueline123'),
        ]);

        $user->assignRole('super-admin');
    }
}
