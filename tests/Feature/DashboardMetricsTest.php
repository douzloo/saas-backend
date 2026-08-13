<?php

use App\Models\Download;
use App\Models\Lead;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;

it('dashboard overview matches raw database calculations', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create(['role' => 'customer']);
    User::factory()->create(['role' => 'staff']);

    $product = Product::factory()->create();
    $user = User::factory()->create(['role' => 'customer']);

    Order::factory()->completed()->create(['user_id' => $user->id, 'total' => 100000, 'paid_at' => now()]);
    Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
    $license = License::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'status' => 'active']);
    LicenseActivation::factory()->create(['license_id' => $license->id, 'is_active' => true]);
    Ticket::factory()->create(['user_id' => $user->id, 'status' => 'open']);
    Lead::factory()->create(['status' => 'new']);
    Download::factory()->create(['download_count' => 5]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

    $response->assertOk();

    $expected = [
        'users.total' => User::count(),
        'users.customers' => User::where('role', 'customer')->count(),
        'users.staff' => User::where('role', 'staff')->count(),
        'orders.total' => Order::count(),
        'orders.completed' => Order::where('status', 'completed')->count(),
        'orders.pending' => Order::where('status', 'pending')->count(),
        'orders.revenue_today' => Order::where('status', 'completed')->whereDate('paid_at', today())->sum('total'),
        'orders.revenue_month' => Order::where('status', 'completed')->whereMonth('paid_at', now()->month)->sum('total'),
        'licenses.total' => License::count(),
        'licenses.active' => License::where('status', 'active')->count(),
        'licenses.activations' => LicenseActivation::count(),
        'licenses.active_activations' => LicenseActivation::where('is_active', true)->count(),
        'tickets.total' => Ticket::count(),
        'tickets.open' => Ticket::open()->count(),
        'leads.total' => Lead::count(),
        'leads.new' => Lead::where('status', 'new')->count(),
        'downloads.total' => Download::sum('download_count'),
    ];

    foreach ($expected as $path => $value) {
        $response->assertJsonPath($path, $value);
    }
});

it('dashboard overview is admin-only and staff cannot access', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
    $this->actingAs($staff, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
});

it('recent orders/leads/tickets endpoints return the latest 10', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Order::factory()->count(12)->create(['user_id' => $user->id]);
    Lead::factory()->count(12)->create();
    Ticket::factory()->count(12)->create(['user_id' => $user->id]);

    $orders = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-orders')->assertOk();
    expect(count($orders->json()))->toBe(10);

    $leads = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-leads')->assertOk();
    expect(count($leads->json()))->toBe(10);

    $tickets = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-tickets')->assertOk();
    expect(count($tickets->json()))->toBe(10);
});
