<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReleaseRequest extends FormRequest
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
            'version' => ['required', 'string', 'max:20'],
            'channel' => ['sometimes', Rule::in(['stable', 'beta', 'rc', 'alpha'])],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'deprecated', 'rolled_back'])],
            'release_notes' => ['nullable', 'string'],
            'changelog' => ['nullable', 'string'],
            'is_force_update' => ['sometimes', 'boolean'],
            'min_app_version' => ['nullable', 'string', 'max:20'],
            'max_app_version' => ['nullable', 'string', 'max:20'],
            'released_at' => ['nullable', 'date'],
        ];
    }
}
