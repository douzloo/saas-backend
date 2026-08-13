<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'product_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
    ];

    // ============================================================
    // Cache Configuration
    // ============================================================

    protected const CACHE_TTL = 3600; // 1 hour

    protected static function cacheKey(string $key, ?int $productId = null): string
    {
        if ($productId !== null) {
            return "settings:product:{$productId}:{$key}";
        }

        return "settings:global:{$key}";
    }

    protected static function productAllCacheKey(int $productId): string
    {
        return "settings:product:{$productId}:all";
    }

    // ============================================================
    // Relationships
    // ============================================================

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('product_id');
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * @param  Builder<Setting>  $query
     * @return Builder<Setting>
     */
    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    // ============================================================
    // Core Methods (Backward Compatible)
    // ============================================================

    /**
     * Get a setting value.
     *
     * @param  string  $key  The setting key
     * @param  mixed  $default  Default value if not found
     * @param  int|null  $productId  Product ID for product-specific settings (null = global)
     */
    public static function get(string $key, mixed $default = null, ?int $productId = null): mixed
    {
        $cacheKey = static::cacheKey($key, $productId);

        return Cache::remember($cacheKey, static::CACHE_TTL, function () use ($key, $default, $productId) {
            // Product-specific lookup
            if ($productId !== null) {
                $setting = static::where('key', $key)
                    ->where('product_id', $productId)
                    ->first();

                if ($setting) {
                    return $setting->value;
                }
            }

            // Global fallback
            $setting = static::where('key', $key)
                ->whereNull('product_id')
                ->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value.
     *
     * @param  string  $key  The setting key
     * @param  mixed  $value  The value to set
     * @param  string  $group  The setting group
     * @param  int|null  $productId  Product ID for product-specific settings (null = global)
     */
    public static function set(string $key, mixed $value, string $group = 'general', ?int $productId = null): void
    {
        static::updateOrCreate(
            ['key' => $key, 'product_id' => $productId],
            ['value' => $value, 'group' => $group]
        );

        // Invalidate cache
        static::invalidateCache($key, $productId);
    }

    /**
     * Get all settings for a group.
     *
     * @param  string  $group  The setting group
     * @param  int|null  $productId  Product ID for product-specific settings (null = global)
     * @return array<string, mixed>
     */
    public static function group(string $group, ?int $productId = null): array
    {
        if ($productId !== null) {
            return static::getProductSettingsForGroup($productId, $group);
        }

        return static::where('group', $group)
            ->whereNull('product_id')
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Delete a setting.
     *
     * @param  string  $key  The setting key
     * @param  int|null  $productId  Product ID for product-specific settings (null = global)
     */
    public static function forget(string $key, ?int $productId = null): void
    {
        static::where('key', $key)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when(! $productId, fn ($q) => $q->whereNull('product_id'))
            ->delete();

        static::invalidateCache($key, $productId);
    }

    // ============================================================
    // Product-Specific Methods (Sprint 1)
    // ============================================================

    /**
     * Get all product-specific settings for a product.
     *
     * @return array<string, mixed>
     */
    public static function getProductSettings(int $productId): array
    {
        $cacheKey = static::productAllCacheKey($productId);

        return Cache::remember($cacheKey, static::CACHE_TTL, function () use ($productId) {
            // Get product-specific settings
            $productSettings = static::where('product_id', $productId)
                ->pluck('value', 'key')
                ->toArray();

            // Get global settings as fallback
            $globalSettings = static::whereNull('product_id')
                ->pluck('value', 'key')
                ->toArray();

            // Merge: product-specific overrides global
            return array_merge($globalSettings, $productSettings);
        });
    }

    /**
     * Get settings for a group, with product-specific overlaid on global.
     *
     * @return array<string, mixed>
     */
    public static function getProductSettingsForGroup(int $productId, string $group): array
    {
        // Get global settings for group
        $globalSettings = static::where('group', $group)
            ->whereNull('product_id')
            ->pluck('value', 'key')
            ->toArray();

        // Get product-specific settings for group
        $productSettings = static::where('group', $group)
            ->where('product_id', $productId)
            ->pluck('value', 'key')
            ->toArray();

        // Merge: product-specific overrides global
        return array_merge($globalSettings, $productSettings);
    }

    /**
     * Delete a product-specific setting.
     */
    public static function deleteProductSetting(string $key, int $productId): void
    {
        static::where('key', $key)
            ->where('product_id', $productId)
            ->delete();

        static::invalidateCache($key, $productId);
    }

    /**
     * Check if a product has a specific setting.
     */
    public static function hasProductSetting(string $key, int $productId): bool
    {
        return static::where('key', $key)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Check if a global setting exists.
     */
    public static function hasGlobal(string $key): bool
    {
        return static::where('key', $key)
            ->whereNull('product_id')
            ->exists();
    }

    // ============================================================
    // Cache Invalidation
    // ============================================================

    /**
     * Invalidate cache for a setting.
     */
    protected static function invalidateCache(string $key, ?int $productId = null): void
    {
        // Invalidate specific cache key
        Cache::forget(static::cacheKey($key, $productId));

        // If product-specific, also invalidate product all cache
        if ($productId !== null) {
            Cache::forget(static::productAllCacheKey($productId));
        }

        // Always invalidate global cache (for fallback scenarios)
        if ($productId !== null) {
            Cache::forget(static::cacheKey($key, null));
        }
    }

    /**
     * Flush all settings cache.
     */
    public static function flushCache(): void
    {
        // This is a nuclear option - use sparingly
        // In production, you'd want to track all keys or use a cache tag
        Cache::tags(['settings'])->flush();
    }
}
