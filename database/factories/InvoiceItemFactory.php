<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(100000, 2000000);
        $quantity = fake()->numberBetween(1, 5);
        $taxRate = 10;

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'description' => implode(' ', [fake()->word(), fake()->word()]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'tax_rate' => $taxRate,
            'tax_amount' => $unitPrice * $quantity * ($taxRate / 100),
            'options' => null,
        ];
    }
}
