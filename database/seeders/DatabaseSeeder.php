<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            LicenseSeeder::class,
            OrderSeeder::class,
            InvoiceSeeder::class,
            PaymentSeeder::class,
            LeadSeeder::class,
            TicketSeeder::class,
            DownloadSeeder::class,
        ]);
    }

    public static function defaultPassword(): string
    {
        return 'password';
    }
}
