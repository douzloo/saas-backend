<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadActivity>
 */
class LeadActivityFactory extends Factory
{
    protected $model = LeadActivity::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['note', 'call', 'email', 'meeting', 'task', 'status_change', 'system']),
            'subject' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'scheduled_at' => null,
            'is_completed' => false,
        ];
    }
}
