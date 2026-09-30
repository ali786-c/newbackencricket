<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Avoid using factory in production seeders because faker is a dev-dependency
        User::updateOrCreate(
            ['email' => 'admin@stumps.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'is_admin' => true,
            ]
        );
    }
}
