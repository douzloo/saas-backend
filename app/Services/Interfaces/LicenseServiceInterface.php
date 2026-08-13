<?php

namespace App\Services\Interfaces;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\Eloquent\Collection;

interface LicenseServiceInterface
{
    public function generateKey(): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): License;

    /**
     * Activate a license.
     *
     * @param  string|null  $domain  Domain name (nullable for machine strategy)
     * @param  string|null  $identifier  Hardware fingerprint or other identifier (nullable for domain strategy)
     * @param  array<string, mixed>  $meta  Additional metadata
     */
    public function activate(License $license, ?string $domain = null, ?string $identifier = null, array $meta = []): bool;

    /**
     * Deactivate a license.
     *
     * @param  string|null  $domain  Domain name (nullable)
     * @param  string|null  $identifier  Identifier (nullable)
     */
    public function deactivate(License $license, ?string $domain = null, ?string $identifier = null): bool;

    /**
     * Verify a license.
     *
     * @param  string|null  $domain  Domain name (nullable)
     * @param  string|null  $identifier  Identifier (nullable)
     * @param  string  $ip  IP address
     */
    /**
     * @return array<string, mixed>
     */
    public function verify(License $license, ?string $domain = null, ?string $identifier = null, string $ip = ''): array;

    /**
     * Send a heartbeat for a license activation.
     *
     * @param  string|null  $domain  Domain name (nullable)
     * @param  string|null  $identifier  Identifier (nullable)
     * @return array<string, mixed>
     */
    public function heartbeat(License $license, ?string $domain = null, ?string $identifier = null): array;

    public function revoke(License $license): bool;

    /**
     * Suspend a license, immediately deactivating all activations.
     */
    public function suspend(License $license, ?string $reason = null): bool;

    /**
     * Reinstate a previously suspended license.
     */
    public function unsuspend(License $license): bool;

    /**
     * Extend the license expiry by a number of days.
     */
    public function renew(License $license, int $days): License;

    /**
     * Upgrade a license's type and/or activation limits.
     *
     * @param  array{type?: string, max_activations?: int}  $data
     */
    public function upgrade(License $license, array $data): License;

    /**
     * Transfer a license to a new user.
     */
    public function transfer(License $license, int $newUserId): License;

    /**
     * List activations whose heartbeat is stale (no recent check-in).
     *
     * @return Collection<int, LicenseActivation>
     */
    public function staleActivations(int $minutes = 30): Collection;

    public function checkExpiration(): void;
}
