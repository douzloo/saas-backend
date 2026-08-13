<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
        $rules = [
            'currency' => ['nullable', 'string', 'max:3'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'billing_details' => ['nullable', 'array'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
        ];

        if ($this->user()?->isStaff()) {
            $rules['status'] = ['nullable', 'in:draft,sent,issued,paid,overdue,cancelled'];
        } else {
            $rules['status'] = ['nullable', 'in:draft'];
        }

        return $rules;
    }
}
