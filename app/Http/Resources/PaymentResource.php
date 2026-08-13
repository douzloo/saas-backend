<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'order_id' => $this->order_id,
            'transaction_id' => $this->transaction_id,
            'reference_id' => $this->reference_id,
            'gateway' => $this->gateway,
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'amount' => $this->amount,
            'refunded_amount' => $this->refunded_amount,
            'refundable_amount' => $this->getRefundableAmount(),
            'currency' => $this->currency,
            'is_paid' => $this->isPaid(),
            'is_refunded' => $this->isRefunded(),
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'invoice' => InvoiceResource::make($this->whenLoaded('invoice')),
        ];
    }
}
