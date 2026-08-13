<?php

namespace App\Models;

use Database\Factories\ProductReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductRelease extends Model
{
    /** @use HasFactory<ProductReleaseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'version',
        'channel',
        'status',
        'release_notes',
        'changelog',
        'is_force_update',
        'min_app_version',
        'max_app_version',
        'released_at',
        'deprecated_at',
        'rolled_back_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_force_update' => 'boolean',
            'released_at' => 'datetime',
            'deprecated_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    // ============================================================
    // Relationships
    // ============================================================

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Download, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class, 'product_release_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeDeprecated($query)
    {
        return $query->where('status', 'deprecated');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeRolledBack($query)
    {
        return $query->where('status', 'rolled_back');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeStable($query)
    {
        return $query->where('channel', 'stable');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeBeta($query)
    {
        return $query->where('channel', 'beta');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('version');
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeForceUpdate($query)
    {
        return $query->where('is_force_update', true);
    }

    /**
     * @param  Builder<ProductRelease>  $query
     * @return Builder<ProductRelease>
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->whereHas('downloads', function ($q) use ($platform) {
            $q->where('platform', $platform);
        });
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published';
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getIsDeprecatedAttribute(): bool
    {
        return $this->status === 'deprecated';
    }

    public function getIsRolledBackAttribute(): bool
    {
        return $this->status === 'rolled_back';
    }

    public function getHasDownloadsAttribute(): bool
    {
        return $this->downloads()->count() > 0;
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'released_at' => now(),
        ]);
    }

    public function deprecate(): void
    {
        $this->update([
            'status' => 'deprecated',
            'deprecated_at' => now(),
        ]);
    }

    public function rollback(): void
    {
        $this->update([
            'status' => 'rolled_back',
            'rolled_back_at' => now(),
        ]);
    }

    public function isCompatibleWithVersion(string $appVersion): bool
    {
        if ($this->min_app_version && version_compare($appVersion, $this->min_app_version, '<')) {
            return false;
        }

        if ($this->max_app_version && version_compare($appVersion, $this->max_app_version, '>')) {
            return false;
        }

        return true;
    }

    public function getLatestDownloadForPlatform(string $platform): ?Download
    {
        return $this->downloads()
            ->where('platform', $platform)
            ->where('status', 'available')
            ->latest('version')
            ->first();
    }
}
