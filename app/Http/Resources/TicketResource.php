<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'priority' => $this->priority,
            'category' => $this->category,
            'department' => $this->department,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'user' => UserResource::make($this->whenLoaded('user')),
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'license' => LicenseResource::make($this->whenLoaded('license')),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'custom_fields' => TicketCustomFieldResource::collection($this->whenLoaded('customFields')),
        ];
    }
}
