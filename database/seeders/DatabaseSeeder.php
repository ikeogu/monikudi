<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'superadmin@monikudi.com'
        ], [
            'name' => 'Super Admin',
            'password' => bcrypt('password'),
        ]);


        User::updateOrCreate([
            'email' => 'admin@monikudi.com',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
        ]);
    }
}
