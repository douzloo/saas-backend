<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Interfaces\InvoiceServiceInterface;
use App\Services\Interfaces\PaymentServiceInterface;
use Illuminate\Support\Facades\DB;

class PaymentService implements PaymentServiceInterface
{
    public function createForInvoice(Invoice $invoice, array $data): Payment
    {
        $data['payment_type'] = 'invoice';
        $data['invoice_id'] = $invoice->id;

        return $this->record($invoice, $data);
    }

    public function createForOrder(Order $order, array $data): Payment
    {
        $data['payment_type'] = 'order';
        $data['order_id'] = $order->id;

        return $this->record($order, $data);
    }

    public function record(Invoice|Order $subject, array $data): Payment
    {
        return DB::transaction(function () use ($subject, $data) {
            $payment = Payment::create(array_merge([
                'transaction_id' => 'TXN-'.strtoupper(uniqid()),
                'gateway' => $data['gateway'] ?? 'manual',
                'status' => $data['status'] ?? 'completed',
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? ($subject instanceof Invoice ? $subject->currency : 'IRR'),
                'reference_id' => $data['reference_id'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'failure_reason' => $data['failure_reason'] ?? null,
                'paid_at' => ($data['status'] ?? 'completed') === 'completed' ? now() : null,
            ], $data));

            if ($subject instanceof Invoice && $payment->status === 'completed') {
                app(InvoiceServiceInterface::class)->markPaid($subject, (float) $payment->amount);
            }

            return $payment;
        });
    }

    public function markCompleted(Payment $payment, array $data = []): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment->update([
                'status' => 'completed',
                'reference_id' => $data['reference_id'] ?? $payment->reference_id,
                'gateway_response' => $data['gateway_response'] ?? $payment->gateway_response,
                'paid_at' => now(),
                'failure_reason' => null,
            ]);

            if ($payment->invoice_id !== null) {
                app(InvoiceServiceInterface::class)->markPaid(
                    $payment->invoice,
                    (float) $payment->amount
                );
            }

            if ($payment->order_id !== null) {
                $order = $payment->order;
                if ($order !== null) {
                    $order->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                    ]);
                }
            }

            return $payment->fresh();
        });
    }

    public function markFailed(Payment $payment, ?string $reason = null): Payment
    {
        $payment->update([
            'status' => 'failed',
            'failure_reason' => $reason ?? 'Payment failed',
        ]);

        return $payment->fresh();
    }

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            if (! in_array($payment->status, ['completed', 'refunded'])) {
                throw new \InvalidArgumentException('Payment cannot be refunded.');
            }

            $refundAmount = $amount ?? (float) ($payment->amount - $payment->refunded_amount);

            if ($refundAmount <= 0 || $refundAmount > $payment->getRefundableAmount()) {
                throw new \InvalidArgumentException('Invalid refund amount.');
            }

            $newRefunded = (float) $payment->refunded_amount + $refundAmount;
            $status = $newRefunded >= (float) $payment->amount ? 'refunded' : $payment->status;

            $payment->update([
                'status' => $status,
                'refunded_amount' => $newRefunded,
                'refunded_at' => $newRefunded >= (float) $payment->amount ? now() : $payment->refunded_at,
                'failure_reason' => $reason,
            ]);

            if ($payment->invoice_id !== null) {
                $invoice = $payment->invoice;
                if ($invoice !== null) {
                    $newBalance = (float) $invoice->balance_due + $refundAmount;
                    $invoice->update([
                        'balance_due' => $newBalance,
                        'status' => $newBalance > 0 ? Invoice::STATUS_SENT : $invoice->status,
                    ]);
                }
            }

            return $payment->fresh();
        });
    }

    public function getMetrics(): array
    {
        $completed = Payment::where('status', 'completed')->sum('amount');
        $refunded = Payment::sum('refunded_amount');
        $total = Payment::count();
        $successful = Payment::where('status', 'completed')->count();

        return [
            'total_received' => (float) $completed,
            'total_refunded' => (float) $refunded,
            'net_revenue' => (float) ($completed - $refunded),
            'total_transactions' => $total,
            'successful_transactions' => $successful,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
        ];
    }
}
