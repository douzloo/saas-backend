<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAssignment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'role',
        'is_primary',
        'assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'assigned_at' => 'datetime',
        ];
    }

    // ============================================================
    // Role Hierarchy Constants
    // ============================================================

    public const ROLE_VIEWER = 'viewer';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_LEVELS = [
        self::ROLE_VIEWER => 1,
        self::ROLE_SUPPORT => 2,
        self::ROLE_MANAGER => 3,
        self::ROLE_ADMIN => 4,
    ];

    // ============================================================
    // Relationships
    // ============================================================

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeManagers($query)
    {
        return $query->where('role', 'manager')
            ->orWhere('role', 'admin');
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeSupport($query)
    {
        return $query->where('role', 'support');
    }

    /**
     * @param  Builder<ProductAssignment>  $query
     * @return Builder<ProductAssignment>
     */
    public function scopeViewers($query)
    {
        return $query->where('role', 'viewer');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getRoleLevelAttribute(): int
    {
        return self::ROLE_LEVELS[$this->role];
    }

    public function getIsManagerOrAboveAttribute(): bool
    {
        return $this->role_level >= self::ROLE_LEVELS[self::ROLE_MANAGER];
    }

    public function getIsSupportOrAboveAttribute(): bool
    {
        return $this->role_level >= self::ROLE_LEVELS[self::ROLE_SUPPORT];
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function canManageRole(string $targetRole): bool
    {
        $targetLevel = self::ROLE_LEVELS[$targetRole] ?? 0;

        return $this->role_level >= $targetLevel;
    }

    public function isHigherOrEqualTo(string $role): bool
    {
        $otherLevel = self::ROLE_LEVELS[$role] ?? 0;

        return $this->role_level >= $otherLevel;
    }

    public function promoteTo(string $role): void
    {
        $this->update(['role' => $role]);
    }

    public function demoteTo(string $role): void
    {
        $this->update(['role' => $role]);
    }

    public function setPrimary(): void
    {
        // Reset other primary assignments for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    public static function getRoleLevel(string $role): int
    {
        return self::ROLE_LEVELS[$role] ?? 0;
    }

    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::ROLE_LEVELS);
    }
}
