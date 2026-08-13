<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class LicenseSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        if ($customers->isEmpty()) {
            $customers = User::factory()->count(5)->create();
        }

        $products = Product::where('requires_activation', true)
            ->orWhere('activation_strategy', '!=', 'none')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(4)->create([
                'requires_activation' => true,
                'activation_strategy' => 'domain',
            ]);
        }

        $licenses = collect();

        foreach ($customers as $customer) {
            $licensesPerCustomer = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $licensesPerCustomer; $i++) {
                $product = $products->random();
                $type = fake()->randomElement(['trial', 'standard', 'standard', 'extended', 'enterprise']);
                $isTrial = $type === 'trial';
                $status = fake()->randomElement(['active', 'active', 'active', 'inactive', 'expired']);
                $maxActivations = $product->default_max_activations ?? fake()->numberBetween(1, 5);

                $license = License::create([
                    'product_id' => $product->id,
                    'user_id' => $customer->id,
                    'label' => fake()->optional(0.5)->words(2, true),
                    'status' => $status,
                    'type' => $type,
                    'max_activations' => $maxActivations,
                    'activation_count' => 0,
                    'price' => $isTrial ? 0 : $product->price,
                    'activated_at' => $status === 'active' ? now()->subDays(fake()->numberBetween(1, 120)) : null,
                    'expires_at' => $isTrial
                        ? now()->addDays(fake()->numberBetween(7, 30))
                        : fake()->optional(0.6)->dateTimeBetween('+1 month', '+1 year'),
                ]);

                $licenses->push($license);
            }
        }

        // Activate some licenses
        $licenses->filter(fn ($license) => $license->status === 'active')
            ->take(fake()->numberBetween(6, 12))
            ->each(function (License $license) {
                $count = fake()->numberBetween(1, min(3, $license->max_activations));

                for ($i = 0; $i < $count; $i++) {
                    LicenseActivation::create([
                        'license_id' => $license->id,
                        'domain' => 'https://'.fake()->domainName(),
                        'identifier' => fake()->optional(0.5)->uuid(),
                        'ip_address' => fake()->ipv4(),
                        'hostname' => fake()->optional()->domainName(),
                        'platform' => fake()->optional()->randomElement(['windows', 'linux', 'macos']),
                        'app_version' => $license->product->version,
                        'fingerprint' => fake()->optional(0.5)->sha256(),
                        'last_heartbeat_at' => now()->subHours(fake()->numberBetween(0, 72)),
                        'is_active' => true,
                    ]);
                }

                $license->update([
                    'activation_count' => $count,
                    'activated_at' => now()->subDays(fake()->numberBetween(1, 120)),
                ]);
            });
    }
}
