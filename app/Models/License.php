<?php

namespace App\Models;

use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'product_id',
        'user_id',
        'label',
        'status',
        'type',
        'max_activations',
        'activation_count',
        'price',
        'activated_at',
        'expires_at',
        'last_check_at',
        'notes',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_activations' => 'integer',
            'activation_count' => 'integer',
            'price' => 'decimal:2',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_check_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (empty($license->key)) {
                $license->key = static::generateKey();
            }
        });
    }

    public static function generateKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        return implode('-', $segments);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LicenseActivation, $this> */
    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /** @return HasMany<LicenseVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(LicenseVerification::class);
    }

    /** @return HasOne<Ticket, $this> */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canActivate(): bool
    {
        return $this->isActive()
            && ! $this->isExpired()
            && $this->activation_count < $this->max_activations;
    }

    public function getRemainingActivationsAttribute(): int
    {
        return max(0, $this->max_activations - $this->activation_count);
    }

    /**
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<', now());
    }

    /**
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
