<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('09#########'),
            'company' => fake()->optional(0.7)->company(),
            'job_title' => fake()->optional(0.6)->jobTitle(),
            'source' => fake()->randomElement(['website', 'referral', 'social_media', 'advertisement', 'cold_call', 'import', 'other']),
            'status' => fake()->randomElement(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost', 'dormant']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'estimated_value' => fake()->numberBetween(100000, 50000000),
            'assigned_to' => User::factory(),
            'product_id' => fake()->boolean(60) ? Product::factory() : null,
            'notes' => fake()->optional(0.5)->paragraph(),
        ];
    }
}
