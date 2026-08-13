<?php

namespace App\Http\Resources;

use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin License
 */
class LicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'type' => $this->type,
            'max_activations' => $this->max_activations,
            'activation_count' => $this->activation_count,
            'remaining_activations' => $this->remaining_activations,
            'price' => $this->price,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_check_at' => $this->last_check_at?->toIso8601String(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => UserResource::make($this->whenLoaded('user')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'activations' => LicenseActivationResource::collection($this->whenLoaded('activations')),
        ];
    }
}
