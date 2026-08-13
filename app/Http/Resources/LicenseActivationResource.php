<?php

namespace App\Http\Resources;

use App\Models\LicenseActivation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenseActivation
 */
class LicenseActivationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'identifier' => $this->identifier,
            'ip_address' => $this->ip_address,
            'hostname' => $this->hostname,
            'platform' => $this->platform,
            'php_version' => $this->php_version,
            'app_version' => $this->app_version,
            'fingerprint' => $this->fingerprint,
            'is_active' => $this->is_active,
            'last_heartbeat_at' => $this->last_heartbeat_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
