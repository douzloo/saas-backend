<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'balance_due' => $this->balance_due,
            'formatted_total' => $this->formatted_total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'billing_details' => $this->billing_details,
            'is_paid' => $this->isPaid(),
            'is_overdue' => $this->isOverdue(),
            'paid_amount' => $this->getPaidAmount(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'user' => UserResource::make($this->whenLoaded('user')),
            'order' => OrderResource::make($this->whenLoaded('order')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('invoiceItems')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
