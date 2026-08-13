<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'source' => $this->source,
            'status' => $this->status,
            'priority' => $this->priority,
            'estimated_value' => $this->estimated_value,
            'notes' => $this->notes,
            'contacted_at' => $this->contacted_at?->toIso8601String(),
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'is_converted' => $this->isConverted(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'activities' => LeadActivityResource::collection($this->whenLoaded('activities')),
            'lead_notes' => LeadNoteResource::collection($this->whenLoaded('notes')),
            'contact' => ContactResource::make($this->whenLoaded('contact')),
        ];
    }
}
