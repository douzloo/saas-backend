<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'version',
        'sku',
        'price',
        'trial_days',
        'icon',
        'screenshot',
        'type',
        'category',
        'status',
        'is_downloadable',
        'requires_activation',
        'activation_strategy',
        'default_max_activations',
        'latest_release_version',
        'max_domains',
        'features',
        'requirements',
        'changelog',
        'installation_guide',
        'download_url',
        'file_size',
        'file_hash',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_days' => 'integer',
            'max_domains' => 'integer',
            'default_max_activations' => 'integer',
            'features' => 'array',
            'requirements' => 'array',
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'is_downloadable' => 'boolean',
            'requires_activation' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ============================================================
    // Relationships
    // ============================================================

    /** @return BelongsToMany<ProductCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product');
    }

    /** @return HasMany<License, $this> */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /** @return HasMany<Download, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /** @return HasMany<ProductRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(ProductRelease::class);
    }

    /** @return HasMany<ProductRelease, $this> */
    public function publishedReleases(): HasMany
    {
        return $this->hasMany(ProductRelease::class)->where('status', 'published');
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Faq, $this> */
    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    /** @return HasMany<ProductAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProductAssignment::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_assignments')
            ->withPivot('role', 'is_primary', 'assigned_at');
    }

    /** @return HasMany<Setting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOrderable($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeDownloadable($query)
    {
        return $query->where('is_downloadable', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithActivation($query)
    {
        return $query->where('requires_activation', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, '', ',').' تومان';
    }

    public function getEffectiveVersionAttribute(): ?string
    {
        return $this->latest_release_version ?? $this->version;
    }

    public function getHasActivationStrategyAttribute(): bool
    {
        return ! is_null($this->activation_strategy);
    }

    public function getActivationStrategyResolvedAttribute(): string
    {
        if ($this->activation_strategy) {
            return $this->activation_strategy;
        }

        return $this->requires_activation ? 'domain' : 'none';
    }

    public function getHasReleasesAttribute(): bool
    {
        return $this->releases()->count() > 0;
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function getLatestDownload(): ?Download
    {
        return $this->downloads()
            ->where('is_active', true)
            ->latest('version')
            ->first();
    }

    public function getLatestRelease(): ?ProductRelease
    {
        return $this->releases()
            ->published()
            ->latest('version')
            ->first();
    }

    public function getLatestReleaseForChannel(string $channel): ?ProductRelease
    {
        return $this->releases()
            ->published()
            ->forChannel($channel)
            ->latest('version')
            ->first();
    }

    public function hasActivation(): bool
    {
        return $this->activation_strategy !== 'none' && $this->requires_activation;
    }

    public function supportsDomainActivation(): bool
    {
        $strategy = $this->activation_strategy_resolved;

        return in_array($strategy, ['domain', 'hybrid']);
    }

    public function supportsMachineActivation(): bool
    {
        $strategy = $this->activation_strategy_resolved;

        return in_array($strategy, ['machine', 'hybrid']);
    }

    /**
     * @return Collection<int, User>
     */
    public function getStaffWithAccess(): Collection
    {
        return User::whereHas('assignments', function ($query) {
            $query->where('product_id', $this->id);
        })->get();
    }
}
