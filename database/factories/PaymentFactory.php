<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'order_id' => null,
            'transaction_id' => 'TXN-'.strtoupper(fake()->unique()->bothify('##########')),
            'reference_id' => fake()->optional()->numerify('#########'),
            'gateway' => fake()->randomElement(['zarinpal', 'manual', 'idpay', 'mellat']),
            'status' => 'completed',
            'payment_type' => 'invoice',
            'amount' => fake()->numberBetween(100000, 5000000),
            'refunded_amount' => 0,
            'currency' => 'IRR',
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}
