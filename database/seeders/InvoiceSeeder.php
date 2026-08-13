<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        if ($customers->isEmpty()) {
            $customers = User::factory()->count(6)->create(['role' => 'customer']);
        }

        $products = Product::all();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(4)->create();
        }

        for ($i = 0; $i < 15; $i++) {
            $customer = $customers->random();
            $status = fake()->randomElement(['paid', 'paid', 'sent', 'sent', 'overdue', 'cancelled']);

            $lineItems = collect();
            $subtotal = 0;

            foreach (range(1, fake()->numberBetween(1, 3)) as $ignored) {
                $product = $products->random();
                $quantity = fake()->numberBetween(1, 3);
                $unitPrice = $product->price;
                $lineTotal = $unitPrice * $quantity;

                $lineItems->push([
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'tax_rate' => 10,
                    'tax_amount' => $lineTotal * 0.10,
                ]);

                $subtotal += $lineTotal;
            }

            $hasDiscount = fake()->boolean(30);
            $discount = $hasDiscount ? fake()->numberBetween(0, (int) $subtotal) : 0;
            $tax = ($subtotal - $discount) * 0.10;
            $total = $subtotal - $discount + $tax;

            $invoice = Invoice::create([
                'user_id' => $customer->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'balance_due' => $status === 'paid' ? 0 : $total,
                'currency' => 'IRR',
                'notes' => fake()->optional(0.3)->sentence(),
                'issued_at' => now()->subDays(fake()->numberBetween(1, 60)),
                'due_at' => now()->addDays(fake()->numberBetween(-10, 30)),
                'paid_at' => $status === 'paid' ? now()->subDays(fake()->numberBetween(0, 50)) : null,
            ]);

            foreach ($lineItems as $item) {
                $invoice->invoiceItems()->create($item);
            }

            if ($status === 'paid') {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => 'TXN-'.strtoupper(uniqid()),
                    'gateway' => fake()->randomElement(['zarinpal', 'idpay', 'mellat', 'manual']),
                    'status' => 'completed',
                    'payment_type' => 'invoice',
                    'amount' => $total,
                    'refunded_amount' => 0,
                    'currency' => 'IRR',
                    'paid_at' => $invoice->paid_at,
                ]);
            }
        }
    }
}
