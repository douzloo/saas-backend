<?php

namespace App\Http\Resources;

use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Download
 */
class DownloadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'formatted_size' => $this->formatted_size,
            'platform' => $this->platform,
            'status' => $this->status,
            'is_stable' => $this->is_stable,
            'changelog' => $this->changelog,
            'download_url' => $this->download_url,
            'download_count' => $this->download_count,
            'created_at' => $this->created_at?->toIso8601String(),

            'product' => ProductResource::make($this->whenLoaded('product')),
            'release' => ProductReleaseResource::make($this->whenLoaded('release')),
        ];
    }
}
