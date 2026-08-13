<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'completed', 'processing', 'cancelled']),
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'currency' => 'IRR',
            'coupon_code' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'paid_at' => now()->subDays(fake()->numberBetween(0, 90)),
        ]);
    }

    public function withItems(int $count = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $subtotal = 0;

            for ($i = 0; $i < $count; $i++) {
                $product = Product::factory()->create();
                $quantity = fake()->numberBetween(1, 3);
                $total = $product->price * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total_price' => $total,
                ]);

                $subtotal += $total;
            }

            $tax = $subtotal * 0.10;
            $order->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
            ]);
        });
    }
}
