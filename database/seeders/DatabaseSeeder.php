<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * This runs on every deploy, so it only creates the default admins when they are
     * missing and never touches the password of an existing account.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'superadmin@monikudi.com',
        ], [
            'name' => 'Super Admin',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('password'),
        ]);

        User::firstOrCreate([
            'email' => 'admin@monikudi.com',
        ], [
            'name' => 'Admin',
            'role' => User::ROLE_ADMIN,
            'password' => bcrypt('password'),
        ]);
    }
}
