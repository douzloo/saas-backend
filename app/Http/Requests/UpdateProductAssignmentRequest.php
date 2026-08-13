<?php

namespace App\Http\Requests;

use App\Models\ProductAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(array_keys(ProductAssignment::ROLE_LEVELS))],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
