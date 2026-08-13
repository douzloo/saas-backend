<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $invoices = Invoice::where('status', 'sent')->orWhere('status', 'overdue')->get();

        if ($invoices->isEmpty()) {
            return;
        }

        foreach ($invoices->random(fake()->numberBetween(1, min(6, $invoices->count()))) as $invoice) {
            $partial = fake()->boolean(40);
            $amount = $partial ? (int) floor($invoice->balance_due / 2) : $invoice->balance_due;

            Payment::create([
                'invoice_id' => $invoice->id,
                'transaction_id' => 'TXN-'.strtoupper(uniqid()),
                'reference_id' => fake()->optional()->numerify('#########'),
                'gateway' => fake()->randomElement(['zarinpal', 'idpay', 'mellat', 'manual']),
                'status' => 'completed',
                'payment_type' => 'invoice',
                'amount' => $amount,
                'refunded_amount' => 0,
                'currency' => 'IRR',
                'paid_at' => now()->subDays(fake()->numberBetween(0, 20)),
            ]);
        }

        $paidInvoices = Invoice::where('status', 'paid')->with('payments')->get();

        if ($paidInvoices->isEmpty()) {
            return;
        }

        foreach ($paidInvoices->random(min(3, $paidInvoices->count())) as $invoice) {
            $payment = $invoice->payments()->where('status', 'completed')->first();

            if (! $payment) {
                continue;
            }

            if (fake()->boolean(50)) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_amount' => $payment->amount,
                    'refunded_at' => now()->subDays(fake()->numberBetween(0, 10)),
                ]);
            } else {
                $refundedAmount = (int) floor($payment->amount / 3);
                $payment->update([
                    'refunded_amount' => $refundedAmount,
                    'refunded_at' => now()->subDays(fake()->numberBetween(0, 10)),
                ]);
            }
        }
    }
}
