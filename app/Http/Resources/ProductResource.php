<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'version' => $this->version,
            'effective_version' => $this->effective_version,
            'sku' => $this->sku,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'trial_days' => $this->trial_days,
            'icon' => $this->icon,
            'screenshot' => $this->screenshot,
            'type' => $this->type,
            'category' => $this->category,
            'status' => $this->status,
            'is_downloadable' => $this->is_downloadable,
            'requires_activation' => $this->requires_activation,
            'activation_strategy' => $this->activation_strategy,
            'max_domains' => $this->max_domains,
            'default_max_activations' => $this->default_max_activations,
            'latest_release_version' => $this->latest_release_version,
            'features' => $this->features,
            'requirements' => $this->requirements,
            'changelog' => $this->changelog,
            'installation_guide' => $this->installation_guide,
            'file_size' => $this->file_size,
            'file_hash' => $this->file_hash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'categories' => ProductCategoryResource::collection($this->whenLoaded('categories')),
            'releases' => ProductReleaseResource::collection($this->whenLoaded('publishedReleases')),
            'faqs' => FaqResource::collection($this->whenLoaded('faqs')),
        ];
    }
}
