<?php

namespace App\Services\Interfaces;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;

interface InvoiceServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{description: string, quantity?: int, unit_price?: float, tax_rate?: float, product_id?: int|string, options?: array<string, mixed>}>  $items
     */
    public function create(User $user, array $data, array $items = []): Invoice;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{description: string, quantity?: int, unit_price?: float, tax_rate?: float, product_id?: int|string, options?: array<string, mixed>}>  $items
     */
    public function update(Invoice $invoice, array $data, array $items = []): Invoice;

    public function delete(Invoice $invoice): bool;

    public function send(Invoice $invoice): Invoice;

    public function cancel(Invoice $invoice): Invoice;

    public function markPaid(Invoice $invoice, ?float $amount = null): Invoice;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * @param  array<int, array{description: string, quantity?: int, unit_price?: float, tax_rate?: float, product_id?: int|string, options?: array<string, mixed>}>  $items
     * @return array<int, InvoiceItem>
     */
    public function syncItems(Invoice $invoice, array $items): array;

    public function recalculate(Invoice $invoice): Invoice;
}
