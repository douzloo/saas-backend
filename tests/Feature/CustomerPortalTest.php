<?php

use App\Models\Download;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;

it('requires authentication for dashboard', function () {
    $this->getJson('/api/portal/dashboard')->assertUnauthorized();
});

it('returns dashboard stats for a customer', function () {
    $user = User::factory()->create();

    Order::factory()->count(2)->for($user)->completed()->create();
    Order::factory()->for($user)->create(['status' => 'pending']);
    License::factory()->count(1)->for($user)->create(['status' => 'active']);
    Ticket::factory()->count(1)->for($user)->open()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'stats' => ['orders', 'licenses', 'tickets', 'downloads'],
            'recent_orders',
            'recent_licenses',
            'recent_tickets',
        ])
        ->assertJsonPath('stats.orders.total', 3)
        ->assertJsonPath('stats.orders.completed', 2)
        ->assertJsonPath('stats.licenses.total', 1)
        ->assertJsonPath('stats.tickets.total', 1);
});

it('returns only the authenticated users dashboard data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Order::factory()->count(3)->for($other)->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/dashboard');

    $response->assertOk()->assertJsonPath('stats.orders.total', 0);
});

it('lists only the authenticated users invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->count(2)->for($user)->create();
    Invoice::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/invoices');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('shows an invoice to its owner', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/portal/invoices/{$invoice->id}");

    $response->assertOk()->assertJsonPath('data.id', $invoice->id);
});

it('forbids showing another users invoice', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $invoice = Invoice::factory()->for($other)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/portal/invoices/{$invoice->id}");

    $response->assertForbidden();
});

it('lists downloads for products the user has a license for', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['is_downloadable' => true]);

    License::factory()->for($user)->for($product)->create(['status' => 'active']);

    Download::factory()->create([
        'product_id' => $product->id,
        'is_active' => true,
        'is_stable' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/downloads');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('does not list downloads for products without an active license', function () {
    $user = User::factory()->create();

    Download::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/downloads');

    $response->assertOk()->assertJsonCount(0, 'data');
});
