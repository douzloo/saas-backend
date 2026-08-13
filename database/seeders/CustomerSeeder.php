<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'علی محمدی', 'phone' => '09121000001', 'company' => 'شرکت فناوری آریا', 'city' => 'تهران'],
            ['name' => 'سارا رضایی', 'phone' => '09121000002', 'company' => 'فروشگاه آنلاین آسمان', 'city' => 'مشهد'],
            ['name' => 'محمد کریمی', 'phone' => '09121000003', 'company' => 'استودیو دیجیتال برنا', 'city' => 'اصفهان'],
            ['name' => 'نگار احمدی', 'phone' => '09121000004', 'company' => null, 'city' => 'شیراز'],
            ['name' => 'رضا نادری', 'phone' => '09121000005', 'company' => 'گروه نرم‌افزاری پارس', 'city' => 'تبریز'],
            ['name' => 'فاطمه حسینی', 'phone' => '09121000006', 'company' => 'آژانس دیجیتال مارکتینگ', 'city' => 'تهران'],
            ['name' => 'حسین موسوی', 'phone' => '09121000007', 'company' => null, 'city' => 'قم'],
            ['name' => 'مریم کاظمی', 'phone' => '09121000008', 'company' => 'داده‌پردازان هوشمند', 'city' => 'کرج'],
            ['name' => 'امیر صادقی', 'phone' => '09121000009', 'company' => 'شرکت بازرگانی آفتاب', 'city' => 'اهواز'],
            ['name' => 'الهام قاسمی', 'phone' => '09121000010', 'company' => null, 'city' => 'رشت'],
            ['name' => 'مهدی عباسی', 'phone' => '09121000011', 'company' => 'پردازش ابری نوین', 'city' => 'تهران'],
            ['name' => 'زهرا جعفری', 'phone' => '09121000012', 'company' => 'فروشگاه اینترنتی کالا', 'city' => 'یزد'],
        ];

        foreach ($customers as $customer) {
            User::firstOrCreate(
                ['email' => strtolower(explode(' ', $customer['name'])[0]).'.douzloo@example.com'],
                [
                    'name' => $customer['name'],
                    'password' => Hash::make(DatabaseSeeder::defaultPassword()),
                    'phone' => $customer['phone'],
                    'role' => 'customer',
                    'status' => 'active',
                    'company' => $customer['company'],
                    'city' => $customer['city'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
