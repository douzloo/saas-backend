<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        if ($customers->isEmpty()) {
            $customers = User::factory()->count(6)->create();
        }

        $products = Product::all();

        for ($i = 0; $i < 10; $i++) {
            $customer = $customers->random();
            $status = fake()->randomElement(['pending', 'completed', 'completed', 'processing', 'cancelled']);

            $order = Order::create([
                'user_id' => $customer->id,
                'status' => $status,
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'currency' => 'IRR',
                'notes' => fake()->optional(0.3)->sentence(),
                'paid_at' => $status === 'completed' ? now()->subDays(fake()->numberBetween(0, 90)) : null,
            ]);

            $subtotal = 0;
            $itemCount = fake()->numberBetween(1, 3);

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $quantity = fake()->numberBetween(1, 2);
                $total = $product->price * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
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

            if ($status === 'completed') {
                $invoice = Invoice::create([
                    'invoice_number' => 'INV-'.strtoupper(uniqid()),
                    'order_id' => $order->id,
                    'user_id' => $customer->id,
                    'status' => 'paid',
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'total' => $order->total,
                    'currency' => 'IRR',
                    'issued_at' => $order->paid_at,
                    'paid_at' => $order->paid_at,
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'payment_type' => 'order',
                    'transaction_id' => 'TXN-'.strtoupper(uniqid()),
                    'gateway' => fake()->randomElement(['zarinpal', 'zarinpal', 'mellat', 'saman', 'pay_ir']),
                    'status' => 'completed',
                    'amount' => $order->total,
                    'currency' => 'IRR',
                    'paid_at' => $order->paid_at,
                ]);

                $order->items()->with('product')->get()->each(function (OrderItem $item) use ($order, $invoice) {
                    for ($k = 0; $k < $item->quantity; $k++) {
                        License::create([
                            'product_id' => $item->product_id,
                            'user_id' => $order->user_id,
                            'label' => 'سفارش '.$order->order_number,
                            'status' => 'active',
                            'type' => 'standard',
                            'max_activations' => $item->product->default_max_activations ?? 1,
                            'activation_count' => 0,
                            'price' => $item->unit_price,
                            'activated_at' => null,
                            'expires_at' => now()->addYear(),
                        ]);
                    }

                    $invoice->invoiceItems()->create([
                        'product_id' => $item->product_id,
                        'description' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'tax_rate' => 10,
                        'tax_amount' => $item->total_price * 0.10,
                    ]);
                });
            }
        }
    }
}
