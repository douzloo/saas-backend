<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRelease>
 */
class ProductReleaseFactory extends Factory
{
    protected $model = ProductRelease::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => '1.'.fake()->numberBetween(1, 999).'.'.fake()->numberBetween(1, 999),
            'channel' => fake()->randomElement(['stable', 'beta']),
            'status' => 'published',
            'release_notes' => fake()->paragraphs(3, true),
            'changelog' => fake()->paragraphs(2, true),
            'is_force_update' => fake()->boolean(20),
            'min_app_version' => '1.0.0',
            'max_app_version' => null,
            'released_at' => now()->subDays(fake()->numberBetween(0, 60)),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'released_at' => null,
        ]);
    }

    public function stable(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'stable',
        ]);
    }
}
