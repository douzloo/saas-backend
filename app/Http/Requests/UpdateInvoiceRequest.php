<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:draft,sent,issued,paid,overdue,cancelled'],
            'currency' => ['nullable', 'string', 'max:3'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'billing_details' => ['nullable', 'array'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
        ];
    }
}
