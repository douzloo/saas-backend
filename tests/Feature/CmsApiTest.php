<?php

use App\Models\Faq;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;

it('lists published pages', function () {
    Page::query()->create([
        'title' => 'درباره ما',
        'slug' => 'about',
        'body' => '<p>متن درباره ما</p>',
        'status' => 'published',
    ]);

    Page::query()->create([
        'title' => 'پیش‌نویس',
        'slug' => 'draft',
        'body' => '<p>پیش‌نویس</p>',
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/pages');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.slug', 'about');
});

it('shows a single published page', function () {
    Page::query()->create([
        'title' => 'قوانین',
        'slug' => 'terms',
        'body' => '<p>قوانین استفاده</p>',
        'status' => 'published',
    ]);

    $this->getJson('/api/pages/terms')
        ->assertOk()
        ->assertJsonPath('slug', 'terms');
});

it('returns 404 for missing or draft page', function () {
    Page::query()->create([
        'title' => 'پیش‌نویس',
        'slug' => 'draft',
        'body' => '<p>پیش‌نویس</p>',
        'status' => 'draft',
    ]);

    $this->getJson('/api/pages/draft')->assertNotFound();
    $this->getJson('/api/pages/nope')->assertNotFound();
});

it('lists published faqs for a product', function () {
    $product = Product::factory()->create();
    Faq::query()->create(['question' => 'سوال ۱', 'answer' => 'پاسخ ۱', 'product_id' => $product->id, 'sort_order' => 1, 'is_published' => true]);
    Faq::query()->create(['question' => 'سوال ۲', 'answer' => 'پاسخ ۲', 'product_id' => $product->id, 'sort_order' => 2, 'is_published' => false]);

    $response = $this->getJson("/api/products/{$product->slug}/faqs");

    $response->assertOk()->assertJsonCount(1);
});

it('returns site settings', function () {
    Setting::set('site_name', 'دوزلو');

    $response = $this->getJson('/api/settings');

    $response->assertOk()
        ->assertJsonPath('site_name', 'دوزلو');
});
