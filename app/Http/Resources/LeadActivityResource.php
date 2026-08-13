<?php

namespace App\Http\Resources;

use App\Models\LeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadActivity
 */
class LeadActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'is_completed' => $this->is_completed,
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
