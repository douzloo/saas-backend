<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseVerification;
use App\Models\Product;
use App\Models\User;
use App\Services\Interfaces\LicenseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LicenseService implements LicenseServiceInterface
{
    public function generateKey(): string
    {
        return License::generateKey();
    }

    public function create(array $data): License
    {
        return License::create(array_merge($data, [
            'key' => $this->generateKey(),
        ]));
    }

    public function activate(License $license, ?string $domain = null, ?string $identifier = null, array $meta = []): bool
    {
        if (! $license->canActivate()) {
            return false;
        }

        // Normalize parameters (empty string = null)
        $domain = $this->normalizeParam($domain);
        $identifier = $this->normalizeParam($identifier);
        $fingerprint = $this->normalizeParam($meta['fingerprint'] ?? null);

        // Resolve activation strategy
        $product = $license->product;
        $resolvedIdentifier = $this->resolveIdentifier($product, $domain, $identifier, $fingerprint);

        // For 'none' strategy, activation is not needed
        if ($resolvedIdentifier === null && $product->activation_strategy_resolved === 'none') {
            return true;
        }

        // Check for existing active activation
        $existing = $this->findExistingActivation($license, $domain, $identifier, $fingerprint);

        if ($existing) {
            $existing->update(array_merge([
                'last_heartbeat_at' => now(),
            ], $meta));

            return true;
        }

        // Create new activation
        DB::transaction(function () use ($license, $domain, $resolvedIdentifier, $meta) {
            $license->activations()->create(array_merge([
                'domain' => $domain,
                'identifier' => $resolvedIdentifier,
                'is_active' => true,
                'last_heartbeat_at' => now(),
            ], $meta));

            $license->increment('activation_count');

            if (is_null($license->activated_at)) {
                $license->update(['activated_at' => now()]);
            }
        });

        Cache::forget("license:{$license->key}");

        return true;
    }

    public function deactivate(License $license, ?string $domain = null, ?string $identifier = null): bool
    {
        // Normalize parameters (empty string = null)
        $domain = $this->normalizeParam($domain);
        $identifier = $this->normalizeParam($identifier);
        $fingerprint = null;

        // Resolve deactivation target
        $product = $license->product;
        $deactivationTarget = $this->resolveDeactivationTarget($product, $domain, $identifier);

        if ($deactivationTarget === null) {
            // For 'none' strategy, nothing to deactivate
            return false;
        }

        // Find the activation to deactivate
        $activation = $license->activations()
            ->where(function ($query) use ($deactivationTarget) {
                $query->where('domain', $deactivationTarget)
                    ->orWhere('identifier', $deactivationTarget);
            })
            ->where('is_active', true)
            ->first();

        if (! $activation) {
            return false;
        }

        $activation->deactivate();
        Cache::forget("license:{$license->key}");

        return true;
    }

    public function verify(License $license, ?string $domain = null, ?string $identifier = null, string $ip = ''): array
    {
        // Normalize parameters (empty string = null)
        $domain = $this->normalizeParam($domain);
        $identifier = $this->normalizeParam($identifier);
        $fingerprint = null;

        // Find activation
        $activation = $this->findActivation($license, $domain, $identifier, $fingerprint);

        $isValid = $license->isActive()
            && ! $license->isExpired()
            && $activation
            && $activation->is_active;

        $reason = null;
        if (! $license->isActive()) {
            $reason = 'license_inactive';
        } elseif ($license->isExpired()) {
            $reason = 'license_expired';
        } elseif (! $activation) {
            $reason = 'activation_not_found';
        } elseif (! $activation->is_active) {
            $reason = 'activation_disabled';
        }

        LicenseVerification::create([
            'license_id' => $license->id,
            'activation_id' => $activation?->id,
            'ip_address' => $ip,
            'domain' => $domain,
            'is_valid' => $isValid,
            'reason' => $reason,
        ]);

        $license->update(['last_check_at' => now()]);

        return [
            'valid' => $isValid,
            'reason' => $reason,
            'license' => [
                'status' => $license->status,
                'expires_at' => $license->expires_at?->toIso8601String(),
                'product' => $license->product->name,
                'version' => $license->product->effective_version,
            ],
        ];
    }

    public function heartbeat(License $license, ?string $domain = null, ?string $identifier = null): array
    {
        // Normalize parameters (empty string = null)
        $domain = $this->normalizeParam($domain);
        $identifier = $this->normalizeParam($identifier);
        $fingerprint = null;

        // Find activation
        $activation = $this->findActivation($license, $domain, $identifier, $fingerprint);

        if (! $activation) {
            return ['success' => false, 'message' => 'Activation not found'];
        }

        $activation->update(['last_heartbeat_at' => now()]);

        return [
            'success' => true,
            'license_status' => $license->status,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'latest_version' => $license->product->effective_version,
        ];
    }

    public function revoke(License $license): bool
    {
        DB::transaction(function () use ($license) {
            $license->activations()->update(['is_active' => false]);
            $license->update(['status' => 'revoked']);
        });

        Cache::forget("license:{$license->key}");

        return true;
    }

    public function suspend(License $license, ?string $reason = null): bool
    {
        DB::transaction(function () use ($license, $reason) {
            $license->activations()->update(['is_active' => false]);
            $license->update([
                'status' => 'suspended',
                'notes' => $reason ?? $license->notes,
            ]);
        });

        Cache::forget("license:{$license->key}");

        return true;
    }

    public function unsuspend(License $license): bool
    {
        if ($license->status !== 'suspended') {
            return false;
        }

        $license->update(['status' => 'active']);

        Cache::forget("license:{$license->key}");

        return true;
    }

    public function renew(License $license, int $days): License
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('Days must be at least 1.');
        }

        $base = $license->expires_at && $license->expires_at->isFuture()
            ? $license->expires_at
            : now();

        $license->update([
            'expires_at' => $base->copy()->addDays($days),
            'status' => 'active',
        ]);

        Cache::forget("license:{$license->key}");

        return $license->fresh();
    }

    public function upgrade(License $license, array $data): License
    {
        $update = [];

        if (isset($data['type'])) {
            $update['type'] = $data['type'];
        }

        if (isset($data['max_activations'])) {
            $max = (int) $data['max_activations'];
            if ($max < $license->activation_count) {
                throw new \InvalidArgumentException(
                    'max_activations cannot be lower than the current activation count.'
                );
            }
            $update['max_activations'] = $max;
        }

        if ($update === []) {
            return $license;
        }

        $license->update($update);

        Cache::forget("license:{$license->key}");

        return $license->fresh();
    }

    public function transfer(License $license, int $newUserId): License
    {
        if (! User::whereKey($newUserId)->exists()) {
            throw new \InvalidArgumentException('Target user does not exist.');
        }

        DB::transaction(function () use ($license, $newUserId) {
            $license->activations()->update(['is_active' => false]);
            $license->update([
                'user_id' => $newUserId,
                'activation_count' => 0,
                'activated_at' => null,
            ]);
        });

        Cache::forget("license:{$license->key}");

        return $license->fresh();
    }

    public function staleActivations(int $minutes = 30): Collection
    {
        return LicenseActivation::active()
            ->stale($minutes)
            ->with('license', 'license.product')
            ->get();
    }

    public function checkExpiration(): void
    {
        License::where('status', 'active')
            ->where('expires_at', '<', now())
            ->each(function ($license) {
                $license->update(['status' => 'expired']);
                $license->activations()->update(['is_active' => false]);
            });
    }

    // ============================================================
    // Identifier Resolution Rules (from Architecture v1.1)
    // ============================================================

    /**
     * Normalize a parameter: empty string = null, trim whitespace.
     */
    protected function normalizeParam(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Validate a machine fingerprint is a well-formed 64-char hex string.
     */
    protected function validateFingerprint(?string $fingerprint): bool
    {
        if ($fingerprint === null) {
            return false;
        }

        return (bool) preg_match('/^[a-f0-9]{64}$/i', $fingerprint);
    }

    /**
     * Resolve the activation strategy from product.
     */
    protected function resolveStrategy(Product $product): string
    {
        if ($product->activation_strategy !== null) {
            return $product->activation_strategy;
        }

        return $product->requires_activation ? 'domain' : 'none';
    }

    /**
     * Resolve the identifier based on strategy.
     *
     *
     * @throws LicenseException if required identifier is missing
     */
    protected function resolveIdentifier(
        Product $product,
        ?string $domain,
        ?string $identifier,
        ?string $fingerprint
    ): ?string {
        $strategy = $this->resolveStrategy($product);

        switch ($strategy) {
            case 'none':
                return null;

            case 'domain':
                if ($domain === null) {
                    throw LicenseException::missingIdentifier('domain', $strategy);
                }

                return $domain;

            case 'machine':
                if ($fingerprint !== null && ! $this->validateFingerprint($fingerprint)) {
                    throw LicenseException::invalidFingerprint($fingerprint);
                }

                $result = $identifier ?? $fingerprint;
                if ($result === null) {
                    throw LicenseException::missingIdentifier('identifier or fingerprint', $strategy);
                }

                return $result;

            case 'hybrid':
                $result = $identifier ?? $domain ?? $fingerprint;
                if ($result === null) {
                    throw LicenseException::missingIdentifier('at least one identifier', $strategy);
                }

                return $result;

            default:
                throw LicenseException::invalidStrategy($strategy);
        }
    }

    /**
     * Resolve the deactivation target.
     */
    protected function resolveDeactivationTarget(
        Product $product,
        ?string $domain,
        ?string $identifier
    ): ?string {
        $strategy = $this->resolveStrategy($product);

        switch ($strategy) {
            case 'none':
                return null;

            case 'domain':
                return $domain;

            case 'machine':
                return $identifier;

            case 'hybrid':
                if ($identifier !== null) {
                    return $identifier;
                }

                return $domain;

            default:
                return null;
        }
    }

    /**
     * Find an existing activation matching the parameters.
     */
    protected function findExistingActivation(
        License $license,
        ?string $domain,
        ?string $identifier,
        ?string $fingerprint
    ): ?LicenseActivation {
        return $license->activations()
            ->where(function ($query) use ($domain, $identifier, $fingerprint) {
                if ($domain !== null) {
                    $query->where('domain', $domain);
                }
                if ($identifier !== null) {
                    $query->orWhere('identifier', $identifier);
                }
                if ($fingerprint !== null) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find an activation matching the parameters.
     */
    protected function findActivation(
        License $license,
        ?string $domain,
        ?string $identifier,
        ?string $fingerprint
    ): ?LicenseActivation {
        return $license->activations()
            ->where(function ($query) use ($domain, $identifier, $fingerprint) {
                if ($domain !== null) {
                    $query->where('domain', $domain);
                }
                if ($identifier !== null) {
                    $query->orWhere('identifier', $identifier);
                }
                if ($fingerprint !== null) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->first();
    }
}
