<?php

namespace Database\Factories;

use App\Models\LeadStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadStage>
 */
class LeadStageFactory extends Factory
{
    protected $model = LeadStage::class;

    public function definition(): array
    {
        return [
            'name' => implode(' ', [fake()->word(), fake()->word()]),
            'key' => fake()->unique()->slug(2),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
