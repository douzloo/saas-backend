<?php

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Product;
use App\Models\User;

it('lists authenticated user licenses', function () {
    $user = User::factory()->create();
    License::factory()->count(2)->for($user)->create();
    License::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/licenses');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('shows a license owned by the user', function () {
    $user = User::factory()->create();
    $license = License::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/portal/licenses/{$license->key}");

    $response->assertOk()
        ->assertJsonPath('data.key', $license->key)
        ->assertJsonStructure(['data' => ['id', 'key', 'status', 'product']]);
});

it('cannot show a license owned by another user', function () {
    $user = User::factory()->create();
    $license = License::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/portal/licenses/{$license->key}");

    $response->assertNotFound();
});

it('activates a license via the desktop endpoint', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
        'max_domains' => 2,
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'max_activations' => 2,
        'activation_count' => 0,
        'expires_at' => now()->addYear(),
    ]);

    $response = $this->postJson('/api/license/activate', [
        'license_key' => $license->key,
        'domain' => 'example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('license.key', $license->key);

    $this->assertDatabaseHas('license_activations', [
        'license_id' => $license->id,
        'domain' => 'example.com',
        'is_active' => true,
    ]);
});

it('verifies a valid license', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'expires_at' => now()->addYear(),
    ]);
    $activation = LicenseActivation::create([
        'license_id' => $license->id,
        'domain' => 'example.com',
        'is_active' => true,
    ]);
    $license->update(['activation_count' => 1]);

    $response = $this->postJson('/api/license/verify', [
        'license_key' => $license->key,
        'domain' => 'example.com',
    ]);

    $response->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonStructure(['license' => ['status', 'expires_at', 'product']]);
});

it('rejects verification with invalid domain', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'expires_at' => now()->addYear(),
    ]);

    $response = $this->postJson('/api/license/verify', [
        'license_key' => $license->key,
        'domain' => 'unknown.com',
    ]);

    $response->assertOk()->assertJsonPath('valid', false);
});

it('rejects expired licenses', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
    ]);
    $license = License::factory()->expired()->create(['product_id' => $product->id]);

    $response = $this->postJson('/api/license/verify', [
        'license_key' => $license->key,
        'domain' => 'example.com',
    ]);

    $response->assertOk()->assertJsonPath('valid', false);
});

it('heartbeat updates activation timestamp', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
    ]);
    $activation = LicenseActivation::create([
        'license_id' => $license->id,
        'domain' => 'example.com',
        'is_active' => true,
        'last_heartbeat_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/license/heartbeat', [
        'license_key' => $license->key,
        'domain' => 'example.com',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->assertNotEquals(
        $activation->fresh()->last_heartbeat_at->toIso8601String(),
        now()->subDay()->toIso8601String(),
    );
});

it('deactivates a license', function () {
    $product = Product::factory()->create([
        'requires_activation' => true,
        'activation_strategy' => 'domain',
    ]);
    $license = License::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'activation_count' => 1,
    ]);
    LicenseActivation::create([
        'license_id' => $license->id,
        'domain' => 'example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/license/deactivate', [
        'license_key' => $license->key,
        'domain' => 'example.com',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('license_activations', [
        'license_id' => $license->id,
        'domain' => 'example.com',
        'is_active' => false,
    ]);
});

it('validates license key is required', function () {
    $this->postJson('/api/license/activate', [])->assertStatus(422);
});
