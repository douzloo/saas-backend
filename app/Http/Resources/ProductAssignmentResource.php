<?php

namespace App\Http\Resources;

use App\Models\ProductAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductAssignment
 */
class ProductAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof ProductAssignment) {
            return $this->assignmentArray($this->resource);
        }

        /** @var User $user */
        $user = $this->resource;
        $assignment = $user->assignments->first();

        return [
            'id' => $assignment?->id,
            'user' => UserResource::make($user),
            'product_id' => $assignment?->product_id,
            'role' => $assignment?->role,
            'is_primary' => $assignment?->is_primary,
            'assigned_at' => $assignment?->assigned_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function assignmentArray(ProductAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'user' => UserResource::make($assignment->user),
            'product_id' => $assignment->product_id,
            'role' => $assignment->role,
            'is_primary' => $assignment->is_primary,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
        ];
    }
}
