<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Interfaces\InvoiceServiceInterface;
use Illuminate\Support\Facades\DB;

class InvoiceService implements InvoiceServiceInterface
{
    public function create(User $user, array $data, array $items = []): Invoice
    {
        return DB::transaction(function () use ($user, $data, $items) {
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'organization_id' => $data['organization_id'] ?? null,
                'status' => $data['status'] ?? Invoice::STATUS_DRAFT,
                'subtotal' => 0,
                'discount' => $data['discount'] ?? 0,
                'tax' => 0,
                'total' => 0,
                'balance_due' => 0,
                'currency' => $data['currency'] ?? 'IRR',
                'notes' => $data['notes'] ?? null,
                'billing_details' => $data['billing_details'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            return $this->recalculate($invoice);
        });
    }

    public function update(Invoice $invoice, array $data, array $items = []): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice->update([
                'status' => $data['status'] ?? $invoice->status,
                'currency' => $data['currency'] ?? $invoice->currency,
                'notes' => $data['notes'] ?? $invoice->notes,
                'billing_details' => $data['billing_details'] ?? $invoice->billing_details,
                'issued_at' => $data['issued_at'] ?? $invoice->issued_at,
                'due_at' => $data['due_at'] ?? $invoice->due_at,
                'discount' => $data['discount'] ?? 0,
            ]);

            if (! empty($items)) {
                $this->syncItems($invoice, $items);
            }

            return $this->recalculate($invoice);
        });
    }

    public function delete(Invoice $invoice): bool
    {
        return (bool) $invoice->delete();
    }

    public function send(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'issued_at' => $invoice->issued_at ?? now(),
        ]);

        return $invoice->fresh();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => Invoice::STATUS_CANCELLED,
        ]);

        return $invoice->fresh();
    }

    public function markPaid(Invoice $invoice, ?float $amount = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount) {
            if ($amount === null || $amount >= (float) $invoice->balance_due) {
                $invoice->update([
                    'status' => Invoice::STATUS_PAID,
                    'balance_due' => 0,
                    'paid_at' => $invoice->paid_at ?? now(),
                ]);
            } else {
                $invoice->update([
                    'balance_due' => max(0, (float) $invoice->balance_due - $amount),
                ]);
            }

            return $invoice->fresh();
        });
    }

    /**
     * @param  array<int, array{description: string, quantity?: int, unit_price?: float, tax_rate?: float, product_id?: int|string, options?: array<string, mixed>}>  $items
     * @return array<int, InvoiceItem>
     */
    public function syncItems(Invoice $invoice, array $items): array
    {
        $invoice->invoiceItems()->delete();

        $created = [];

        foreach ($items as $item) {
            $unitPrice = $item['unit_price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $taxRate = $item['tax_rate'] ?? 0;
            $totalPrice = $unitPrice * $quantity;
            $taxAmount = $totalPrice * ($taxRate / 100);

            $created[] = $invoice->invoiceItems()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'options' => $item['options'] ?? null,
            ]);
        }

        return $created;
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $items = $invoice->invoiceItems()->get();

        $subtotal = $items->sum(fn (InvoiceItem $item) => (float) $item->total_price);
        $tax = $items->sum(fn (InvoiceItem $item) => (float) $item->tax_amount);
        $discount = (float) $invoice->discount;
        $total = max(0, $subtotal - $discount + $tax);

        $paidAmount = (float) $invoice->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $balanceDue = max(0, $total - $paidAmount);

        $status = $invoice->status;
        if ($status === Invoice::STATUS_SENT && $invoice->due_at !== null && $invoice->due_at->isPast()) {
            $status = Invoice::STATUS_OVERDUE;
        }
        if ($balanceDue <= 0 && $total > 0 && in_array($status, [Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])) {
            $status = Invoice::STATUS_PAID;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'balance_due' => $balanceDue,
            'status' => $status,
            'paid_at' => $status === Invoice::STATUS_PAID ? ($invoice->paid_at ?? now()) : $invoice->paid_at,
        ]);

        return $invoice->fresh();
    }

    public function getMetrics(): array
    {
        $totalRevenue = (float) Payment::where('status', 'completed')
            ->where('payment_type', 'invoice')
            ->sum('amount');

        $monthlyRevenue = (float) Payment::where('status', 'completed')
            ->where('payment_type', 'invoice')
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $outstanding = (float) Invoice::outstanding()->sum('balance_due');
        $overdue = (float) Invoice::overdue()->sum('balance_due');

        $totalPayments = Payment::where('payment_type', 'invoice')->count();
        $completedPayments = Payment::where('payment_type', 'invoice')
            ->where('status', 'completed')
            ->count();

        $successRate = $totalPayments > 0
            ? round(($completedPayments / $totalPayments) * 100, 2)
            : 0;

        return [
            'revenue' => [
                'total' => $totalRevenue,
                'monthly' => $monthlyRevenue,
                'success_rate' => $successRate,
            ],
            'invoices' => [
                'outstanding' => $outstanding,
                'overdue' => $overdue,
                'total_outstanding' => Invoice::outstanding()->count(),
                'total_overdue' => Invoice::overdue()->count(),
            ],
        ];
    }
}
