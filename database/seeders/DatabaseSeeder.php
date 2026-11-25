<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the first administrator so someone can log in and make the other accounts.
     * Safe to run more than once: an existing "admin" account (and its password) is left alone.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => env('ADMIN_PASSWORD') ?: 'ChangeMe!2026',
                'role' => User::ROLE_ADMINISTRATOR,
            ]
        );
    }
}
