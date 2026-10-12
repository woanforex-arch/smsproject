<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'phone' => '+254700000000',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Door Staff',
                'phone' => '+254700000001',
                'password' => Hash::make('password'),
                'role' => UserRole::Staff,
                'is_active' => true,
            ],
        );

        $this->command?->info('Seeded admin@example.com / staff@example.com (password: "password")');
    }
}