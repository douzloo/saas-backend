<?php

use App\Models\Download;
use App\Models\DownloadLog;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;

it('filters downloads by release channel', function () {
    $product = Product::factory()->create();

    $stableRelease = ProductRelease::factory()->stable()->create(['product_id' => $product->id]);
    $betaRelease = ProductRelease::factory()->create(['product_id' => $product->id, 'channel' => 'beta']);

    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $stableRelease->id, 'is_stable' => true]);
    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $betaRelease->id, 'is_stable' => false]);

    $response = $this->getJson('/api/downloads/latest?channel=beta');

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.product_release_id', $betaRelease->id);
});

it('latest downloads defaults to stable channel', function () {
    $product = Product::factory()->create();
    $stableRelease = ProductRelease::factory()->stable()->create(['product_id' => $product->id]);
    $betaRelease = ProductRelease::factory()->create(['product_id' => $product->id, 'channel' => 'beta']);

    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $stableRelease->id, 'is_stable' => true]);
    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $betaRelease->id, 'is_stable' => false]);

    $response = $this->getJson('/api/downloads/latest');

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.product_release_id', $stableRelease->id);
});

it('lists downloads per product with channel filter', function () {
    $product = Product::factory()->create();
    $stableRelease = ProductRelease::factory()->stable()->create(['product_id' => $product->id]);
    $betaRelease = ProductRelease::factory()->create(['product_id' => $product->id, 'channel' => 'beta']);

    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $stableRelease->id, 'is_stable' => true, 'platform' => 'universal']);
    Download::factory()->create(['product_id' => $product->id, 'product_release_id' => $betaRelease->id, 'is_stable' => false, 'platform' => 'universal']);

    $response = $this->getJson("/api/downloads/product/{$product->slug}?channel=beta");

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.product_release_id', $betaRelease->id);
});

it('allows download of a free product without a license', function () {
    $product = Product::factory()->free()->create(['is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => true]);

    $response = $this->getJson("/api/downloads/{$download->id}");

    $response->assertOk()->assertJsonPath('filename', $download->original_filename);
});

it('rejects download of an activation-gated product without a license', function () {
    $product = Product::factory()->create(['requires_activation' => true, 'is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => true]);

    $response = $this->getJson("/api/downloads/{$download->id}");

    $response->assertStatus(403);
});

it('allows download of a gated product with a valid license key', function () {
    $product = Product::factory()->create(['requires_activation' => true, 'is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    $license = License::factory()->for($product)->create(['status' => 'active']);

    $response = $this->getJson("/api/downloads/{$download->id}?license_key={$license->key}");

    $response->assertOk();

    $this->assertDatabaseHas('download_logs', ['download_id' => $download->id, 'license_id' => $license->id]);
});

it('allows download of a gated product for an authenticated owner', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['requires_activation' => true, 'is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    License::factory()->for($user)->for($product)->create(['status' => 'active']);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/downloads/{$download->id}");

    $response->assertOk();
});

it('resolves the bearer-token owner on the public download route', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['requires_activation' => true, 'is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    License::factory()->for($user)->for($product)->create(['status' => 'active']);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/downloads/{$download->id}")->assertOk();
});

it('rejects download of an inactive download', function () {
    $product = Product::factory()->free()->create(['is_downloadable' => true]);
    $download = Download::factory()->create(['product_id' => $product->id, 'is_active' => false]);

    $response = $this->getJson("/api/downloads/{$download->id}");

    $response->assertStatus(404);
});

it('requires admin to view download stats', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/admin/downloads/stats')
        ->assertForbidden();
});

it('returns download stats for admin', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['name' => 'Product Alpha']);

    $win = Download::factory()->create(['product_id' => $product->id, 'download_count' => 100, 'platform' => 'windows']);
    Download::factory()->create(['product_id' => $product->id, 'download_count' => 50, 'platform' => 'linux']);
    DownloadLog::factory()->count(3)->create(['download_id' => $win->id]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/downloads/stats');

    $response->assertOk()
        ->assertJsonPath('totals.download_count', 150)
        ->assertJsonPath('totals.log_entries', 3)
        ->assertJsonCount(1, 'by_product')
        ->assertJsonPath('by_product.0.product_name', 'Product Alpha')
        ->assertJsonCount(2, 'by_platform')
        ->assertJsonPath('by_platform.0.platform', 'windows');
});

it('lists downloads for admin with filters', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    Download::factory()->create(['product_id' => $product->id, 'status' => 'available']);
    Download::factory()->create(['product_id' => $product->id, 'status' => 'deprecated']);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/downloads?status=deprecated');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'deprecated');
});

it('requires admin to view download logs', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/admin/download-logs')
        ->assertForbidden();
});

it('lists download audit logs for admin', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $download = Download::factory()->create();

    DownloadLog::factory()->create(['download_id' => $download->id, 'user_id' => $user->id]);
    DownloadLog::factory()->count(2)->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/download-logs');

    $response->assertOk()->assertJsonCount(3, 'data')->assertJsonCount(3, 'data');
});

it('filters download audit logs by user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $download = Download::factory()->create();

    DownloadLog::factory()->create(['download_id' => $download->id, 'user_id' => $user->id]);
    DownloadLog::factory()->count(2)->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/download-logs?user_id={$user->id}");

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.id', $user->id);
});
