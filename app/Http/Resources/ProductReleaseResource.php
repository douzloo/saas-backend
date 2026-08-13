<?php

namespace App\Http\Resources;

use App\Models\ProductRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductRelease
 */
class ProductReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'channel' => $this->channel,
            'status' => $this->status,
            'release_notes' => $this->release_notes,
            'changelog' => $this->changelog,
            'is_force_update' => $this->is_force_update,
            'min_app_version' => $this->min_app_version,
            'max_app_version' => $this->max_app_version,
            'released_at' => $this->released_at?->toIso8601String(),
            'deprecated_at' => $this->deprecated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
