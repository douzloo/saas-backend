<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
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
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'gateway' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:3'],
        ];
    }
}
