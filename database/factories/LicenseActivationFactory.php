<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseActivation>
 */
class LicenseActivationFactory extends Factory
{
    protected $model = LicenseActivation::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'domain' => fake()->domainName(),
            'identifier' => null,
            'ip_address' => fake()->ipv4(),
            'hostname' => null,
            'platform' => fake()->randomElement(['windows', 'macos', 'linux', 'web']),
            'php_version' => fake()->randomElement(['8.1', '8.2', '8.3']),
            'app_version' => fake()->randomElement(['1.0.0', '1.1.0', '2.0.0']),
            'fingerprint' => fake()->sha256(),
            'last_heartbeat_at' => now(),
            'is_active' => true,
        ];
    }

    public function stale(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'last_heartbeat_at' => now()->subMinutes($minutes),
        ]);
    }
}
