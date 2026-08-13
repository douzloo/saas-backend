<?php

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;

it('requires admin to list licenses', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/admin/licenses')
        ->assertForbidden();
});

it('lists licenses as admin', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->count(3)->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/licenses');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters licenses by status as admin', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create(['status' => 'active']);
    License::factory()->create(['status' => 'suspended']);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/licenses?status=suspended');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('suspends a license and deactivates its activations', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->activated()->create(['status' => 'active']);
    LicenseActivation::factory()->create(['license_id' => $license->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/licenses/{$license->id}/suspend", ['reason' => 'بدلیل تخلف']);

    $response->assertOk()->assertJsonPath('data.status', 'suspended');

    $this->assertDatabaseHas('licenses', ['id' => $license->id, 'status' => 'suspended']);
    $this->assertDatabaseHas('license_activations', ['license_id' => $license->id, 'is_active' => false]);
});

it('unsuspends a license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['status' => 'suspended']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/licenses/{$license->id}/unsuspend");

    $response->assertOk()->assertJsonPath('data.status', 'active');
});

it('revokes a license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['status' => 'active']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/licenses/{$license->id}/revoke");

    $response->assertOk()->assertJsonPath('data.status', 'revoked');
});

it('renews a license by extending expiry', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['status' => 'active', 'expires_at' => now()->addMonth()]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/licenses/{$license->id}/renew", ['days' => 365]);

    $response->assertOk();

    $this->assertTrue($license->fresh()->expires_at->gt(now()->addMonth()));
});

it('validates renew days are positive', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/licenses/{$license->id}/renew", ['days' => 0]);

    $response->assertStatus(422)->assertJsonValidationErrors('days');
});

it('upgrades a license type and activation limits', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['type' => 'trial', 'max_activations' => 1]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/licenses/{$license->id}/upgrade", [
            'type' => 'enterprise',
            'max_activations' => 10,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.type', 'enterprise')
        ->assertJsonPath('data.max_activations', 10);
});

it('rejects upgrade below current activation count', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['max_activations' => 5, 'activation_count' => 3]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/licenses/{$license->id}/upgrade", ['max_activations' => 2]);

    $response->assertStatus(422);
});

it('transfers a license to another user and resets activations', function () {
    $admin = User::factory()->admin()->create();
    $oldOwner = User::factory()->create();
    $newOwner = User::factory()->create();
    $license = License::factory()->for($oldOwner)->activated()->create(['status' => 'active']);
    LicenseActivation::factory()->create(['license_id' => $license->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/licenses/{$license->id}/transfer", ['user_id' => $newOwner->id]);

    $response->assertOk()->assertJsonPath('data.user.id', $newOwner->id);

    $this->assertDatabaseHas('licenses', ['id' => $license->id, 'user_id' => $newOwner->id, 'activation_count' => 0]);
    $this->assertDatabaseHas('license_activations', ['license_id' => $license->id, 'is_active' => false]);
});

it('lists stale activations', function () {
    $admin = User::factory()->admin()->create();
    $active = LicenseActivation::factory()->create(['is_active' => true, 'last_heartbeat_at' => now()]);
    $stale = LicenseActivation::factory()->create(['is_active' => true, 'last_heartbeat_at' => now()->subHours(2)]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/licenses/stale-activations');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stale->id);
});

it('shows license activations as admin', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    LicenseActivation::factory()->count(2)->create(['license_id' => $license->id]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/licenses/{$license->id}/activations");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('rejects activation with malformed machine fingerprint', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'machine',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'max_activations' => 2,
        'expires_at' => now()->addYear(),
    ]);

    $response = $this->postJson('/api/license/activate', [
        'license_key' => $license->key,
        'identifier' => 'not-a-valid-fingerprint',
    ], ['X-Fingerprint' => 'not-a-valid-fingerprint']);

    $response->assertStatus(422)
        ->assertJsonPath('reason', 'invalid_fingerprint');
});

it('activates machine strategy with a valid fingerprint', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'machine',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'max_activations' => 2,
        'expires_at' => now()->addYear(),
    ]);

    $fingerprint = str_repeat('ab', 32);

    $response = $this->postJson('/api/license/activate', [
        'license_key' => $license->key,
    ], ['X-Fingerprint' => $fingerprint]);

    $response->assertOk();

    $this->assertDatabaseHas('license_activations', [
        'license_id' => $license->id,
        'fingerprint' => $fingerprint,
        'is_active' => true,
    ]);
});
