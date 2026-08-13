<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'version' => ['nullable', 'string', 'max:20'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'icon' => ['nullable', 'string', 'max:255'],
            'screenshot' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:standard,pro,enterprise'],
            'category' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'is_downloadable' => ['nullable', 'boolean'],
            'requires_activation' => ['nullable', 'boolean'],
            'activation_strategy' => ['nullable', 'in:none,domain,machine,hybrid'],
            'max_domains' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'default_max_activations' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string'],
            'changelog' => ['nullable', 'string'],
            'installation_guide' => ['nullable', 'string'],
            'download_url' => ['nullable', 'url'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'file_hash' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:product_categories,id'],
        ];
    }
}
