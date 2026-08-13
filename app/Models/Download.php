<?php

namespace App\Models;

use Database\Factories\DownloadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $total_downloads
 */
class Download extends Model
{
    /** @use HasFactory<DownloadFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_release_id',
        'version',
        'filename',
        'original_filename',
        'mime_type',
        'file_size',
        'file_hash',
        'download_url',
        'changelog',
        'platform',
        'status',
        'is_active',
        'is_stable',
        'requirements',
        'download_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_active' => 'boolean',
            'is_stable' => 'boolean',
            'requirements' => 'array',
            'download_count' => 'integer',
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

    /** @return BelongsTo<ProductRelease, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(ProductRelease::class, 'product_release_id');
    }

    /** @return HasMany<DownloadLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeStable($query)
    {
        return $query->where('is_stable', true);
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeForRelease($query, int $releaseId)
    {
        return $query->where('product_release_id', $releaseId);
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeForChannel($query, string $channel)
    {
        if ($channel === 'stable') {
            return $query->where(function ($q) {
                $q->where('is_stable', true)
                    ->orWhereHas('release', function ($r) {
                        $r->where('channel', 'stable');
                    });
            });
        }

        return $query->whereHas('release', function ($r) use ($channel) {
            $r->where('channel', $channel);
        });
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeDeprecated($query)
    {
        return $query->where('status', 'deprecated');
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeRemoved($query)
    {
        return $query->where('status', 'removed');
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeWithRelease($query)
    {
        return $query->whereNotNull('product_release_id');
    }

    /**
     * @param  Builder<Download>  $query
     * @return Builder<Download>
     */
    public function scopeWithoutRelease($query)
    {
        return $query->whereNull('product_release_id');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getHasReleaseAttribute(): bool
    {
        return ! is_null($this->product_release_id);
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function deprecate(): void
    {
        $this->update(['status' => 'deprecated']);
    }

    public function remove(): void
    {
        $this->update(['status' => 'removed', 'is_active' => false]);
    }
}
