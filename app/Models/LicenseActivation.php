<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseActivation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'license_id',
        'domain',
        'identifier',
        'ip_address',
        'hostname',
        'platform',
        'php_version',
        'app_version',
        'fingerprint',
        'last_heartbeat_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ============================================================
    // Relationships
    // ============================================================

    /** @return BelongsTo<License, $this> */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /** @return HasMany<LicenseVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(LicenseVerification::class, 'activation_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeForLicense($query, int $licenseId)
    {
        return $query->where('license_id', $licenseId);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeForDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeForIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeForFingerprint($query, string $fingerprint)
    {
        return $query->where('fingerprint', $fingerprint);
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeWithHeartbeat($query)
    {
        return $query->whereNotNull('last_heartbeat_at');
    }

    /**
     * @param  Builder<LicenseActivation>  $query
     * @return Builder<LicenseActivation>
     */
    public function scopeStale($query, int $minutes = 30)
    {
        return $query->where('last_heartbeat_at', '<', now()->subMinutes($minutes));
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getEffectiveIdentifierAttribute(): ?string
    {
        return $this->identifier ?? $this->domain;
    }

    public function getIsStaleAttribute(): bool
    {
        if (! $this->last_heartbeat_at) {
            return true;
        }

        return $this->last_heartbeat_at->diffInMinutes(now()) > 30;
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
        $this->license->decrement('activation_count');
    }

    public function reactivate(): void
    {
        $this->update(['is_active' => true]);
        $this->license->increment('activation_count');
    }

    public function updateHeartbeat(): void
    {
        $this->update(['last_heartbeat_at' => now()]);
    }

    public function matchesIdentifier(string $identifier): bool
    {
        return $this->identifier === $identifier || $this->domain === $identifier;
    }

    public function matchesFingerprint(string $fingerprint): bool
    {
        return $this->fingerprint === $fingerprint;
    }
}
