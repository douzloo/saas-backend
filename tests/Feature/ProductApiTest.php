<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRelease;
use App\Models\User;

it('lists active products', function () {
    Product::factory()->count(3)->create();

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'price'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

it('filters products by category slug', function () {
    $category = ProductCategory::factory()->create(['slug' => 'web-app']);

    $matching = Product::factory()->create();
    $matching->categories()->attach($category->id);

    Product::factory()->count(2)->create();

    $response = $this->getJson('/api/products?category=web-app');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $matching->slug);
});

it('does not list inactive products', function () {
    Product::factory()->create(['status' => 'active']);
    Product::factory()->count(2)->inactive()->create();

    $response = $this->getJson('/api/products');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('shows a single product with relationships', function () {
    $product = Product::factory()->create();
    $product->categories()->attach(ProductCategory::factory()->create()->id);

    $response = $this->getJson("/api/products/{$product->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.slug', $product->slug)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'categories', 'faqs', 'releases'],
        ]);
});

it('returns 404 for missing product', function () {
    $this->getJson('/api/products/does-not-exist')->assertNotFound();
});

it('lists product categories', function () {
    ProductCategory::factory()->count(2)->create(['parent_id' => null]);

    $response = $this->getJson('/api/products/categories');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('lists published product releases', function () {
    $product = Product::factory()->create();
    ProductRelease::factory()->for($product)->create(['version' => '1.1.0', 'status' => 'published']);
    ProductRelease::factory()->for($product)->create(['version' => '1.2.0', 'status' => 'published']);
    ProductRelease::factory()->for($product)->draft()->create(['version' => '1.3.0']);

    $response = $this->getJson("/api/products/{$product->slug}/releases");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('returns latest published release', function () {
    $product = Product::factory()->create();
    ProductRelease::factory()->for($product)->create(['version' => '2.0.0', 'status' => 'published']);

    $response = $this->getJson("/api/products/{$product->slug}/releases/latest");

    $response->assertOk()->assertJsonPath('data.version', '2.0.0');
});

it('allows admin to create a product', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/products', [
            'name' => 'پنل مدیریتی',
            'price' => 990000,
            'type' => 'pro',
            'status' => 'active',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'پنل مدیریتی')
        ->assertJsonPath('data.price', '990000.00');
});

it('rejects product creation for non-staff', function () {
    $customer = User::factory()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/admin/products', ['name' => 'X']);

    $response->assertForbidden();
});

it('validates product creation', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/products', ['name' => '']);

    $response->assertStatus(422)->assertJsonValidationErrors('name');
});
