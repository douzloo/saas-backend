<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assigned_to' => null,
            'product_id' => null,
            'license_id' => null,
            'subject' => fake()->sentence(6),
            'status' => fake()->randomElement(['open', 'in_progress', 'waiting_reply', 'resolved', 'closed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'category' => fake()->randomElement(['general', 'technical', 'billing', 'bug_report', 'feature_request', 'other']),
            'department' => null,
            'is_internal' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    public function withMessage(): static
    {
        return $this->afterCreating(function (Ticket $ticket) {
            $ticket->messages()->create([
                'user_id' => $ticket->user_id,
                'body' => fake()->paragraph(),
                'is_staff_reply' => false,
                'is_internal_note' => false,
            ]);
        });
    }
}
