<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@douzloo.com'],
            [
                'name' => 'مدیر سیستم',
                'password' => Hash::make('password'),
                'phone' => '09120000000',
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'staff@douzloo.com'],
            [
                'name' => 'پشتیبان فروش',
                'password' => Hash::make('password'),
                'phone' => '09120000001',
                'role' => 'staff',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'customer@douzloo.com'],
            [
                'name' => 'مشتری نمونه',
                'password' => Hash::make('password'),
                'phone' => '09120000002',
                'role' => 'customer',
                'status' => 'active',
                'company' => 'شرکت نمونه',
                'email_verified_at' => now(),
            ],
        );

        User::factory()->count(8)->create();
    }
}
