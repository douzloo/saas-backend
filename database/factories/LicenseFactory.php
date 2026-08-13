<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'label' => fake()->optional()->company(),
            'status' => 'active',
            'type' => fake()->randomElement(['trial', 'standard', 'extended', 'enterprise']),
            'max_activations' => fake()->numberBetween(1, 5),
            'activation_count' => 0,
            'price' => fake()->randomElement([0, 149000, 249000, 490000, 990000]),
            'activated_at' => null,
            'expires_at' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+1 year'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'trial',
            'price' => 0,
            'expires_at' => now()->addDays(fake()->numberBetween(7, 30)),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ]);
    }

    public function activated(): static
    {
        return $this->state(fn (array $attributes) => [
            'activated_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'activation_count' => fake()->numberBetween(1, 3),
        ]);
    }
}
