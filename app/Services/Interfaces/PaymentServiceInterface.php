<?php

namespace App\Services\Interfaces;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;

interface PaymentServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForInvoice(Invoice $invoice, array $data): Payment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForOrder(Order $order, array $data): Payment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Invoice|Order $subject, array $data): Payment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markCompleted(Payment $payment, array $data = []): Payment;

    public function markFailed(Payment $payment, ?string $reason = null): Payment;

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}
