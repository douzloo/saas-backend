<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_staff_reply' => $this->is_staff_reply,
            'is_internal_note' => $this->is_internal_note,
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => UserResource::make($this->whenLoaded('user')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
