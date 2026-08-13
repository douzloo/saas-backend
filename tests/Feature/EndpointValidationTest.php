<?php

use App\Models\Download;
use App\Models\Faq;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRelease;
use App\Models\Ticket;
use App\Models\User;

/**
 * Public / anonymous endpoints.
 */
it('public: products index/categories/show/faqs/releases/latest', function () {
    $product = Product::factory()->create();
    ProductCategory::factory()->create(['slug' => 'web-app']);
    Faq::query()->create(['question' => 'q', 'answer' => 'a', 'product_id' => $product->id, 'is_published' => true]);
    ProductRelease::factory()->for($product)->create(['version' => '2.0.0', 'status' => 'published']);

    $this->getJson('/api/products')->assertOk();
    $this->getJson('/api/products/categories')->assertOk();
    $this->getJson("/api/products/{$product->slug}")->assertOk();
    $this->getJson("/api/products/{$product->slug}/faqs")->assertOk();
    $this->getJson("/api/products/{$product->slug}/releases")->assertOk();
    $this->getJson("/api/products/{$product->slug}/releases/latest")->assertOk();
    $this->getJson('/api/settings')->assertOk();
    $this->getJson('/api/pages')->assertOk();
});

it('public: downloads listing and single', function () {
    $product = Product::factory()->create(['is_downloadable' => true]);
    Download::factory()->create(['product_id' => $product->id]);

    $this->getJson('/api/downloads')->assertOk();
    $this->getJson('/api/downloads/latest')->assertOk();
    $this->getJson("/api/downloads/product/{$product->slug}")->assertOk();
});

it('public: license verify endpoint', function () {
    $this->postJson('/api/license/verify', ['license_key' => 'invalid'])->assertNotFound();
    $this->postJson('/api/license/verify', [])->assertStatus(422);
});

it('public: contact endpoint', function () {
    $this->postJson('/api/contact', ['name' => 'x', 'email' => 'a@b.c', 'message' => 'hello'])
        ->assertStatus(201);
});

/**
 * Auth endpoints.
 */
it('auth: register/login/me/logout', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'reg@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(201);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'reg@example.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('token');
    $this->withToken($token)->getJson('/api/auth/user')->assertOk();
    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
});

it('auth: customer can update profile and password', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/profile', ['name' => 'New Name'])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertOk();
});

/**
 * Customer portal.
 */
it('portal: dashboard, downloads, invoices, licenses', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/portal/dashboard')->assertOk();
    $this->actingAs($user, 'sanctum')->getJson('/api/portal/downloads')->assertOk();
    $this->actingAs($user, 'sanctum')->getJson('/api/portal/invoices')->assertOk();
    $this->actingAs($user, 'sanctum')->getJson('/api/portal/licenses')->assertOk();
});

/**
 * Admin: products, releases, assignments.
 */
it('admin: products CRUD and releases', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    $release = ProductRelease::factory()->for($product)->draft()->create();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/products/{$product->slug}/releases")->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/products/{$product->slug}/releases/{$release->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/products/{$product->slug}/releases", [
        'version' => '3.1.4',
        'channel' => 'stable',
        'status' => 'draft',
    ])->assertStatus(201);
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/products/{$product->slug}/releases/{$release->id}/publish")->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/products/{$product->slug}/staff")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/products/{$product->slug}/staff", ['user_id' => $staff->id, 'role' => 'support'])->assertStatus(201);
    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/users/{$staff->id}/products")->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$product->slug}", ['name' => 'Updated'])->assertOk();
});

/**
 * Admin: licenses.
 */
it('admin: license management', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['requires_activation' => true]);
    $user = User::factory()->create();
    $license = License::factory()->create(['product_id' => $product->id, 'user_id' => $user->id]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/licenses')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/licenses/{$license->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/admin/licenses/{$license->id}/activations")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/licenses/{$license->id}/suspend")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/licenses/{$license->id}/unsuspend")->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/licenses/{$license->id}/renew", ['days' => 30])->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson("/api/admin/licenses/{$license->id}/upgrade", ['type' => 'extended'])->assertOk();
});

/**
 * Admin: downloads.
 */
it('admin: download management', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/downloads')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/downloads/stats')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/download-logs')->assertOk();
});

/**
 * Admin: dashboard + billing metrics.
 */
it('admin: dashboard and billing metrics', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-orders')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-leads')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard/recent-tickets')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/billing/metrics')->assertOk();
});

/**
 * CRM: leads, pipeline, stages, notes, activities.
 */
it('crm: full lead lifecycle', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $lead = Lead::factory()->create(['assigned_to' => $admin->id]);
    $stage = LeadStage::factory()->create();
    $note = LeadNote::factory()->create(['lead_id' => $lead->id, 'user_id' => $admin->id]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/crm/leads')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/crm/leads/pipeline')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/crm/leads/stats')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/crm/leads/reminders')->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/crm/leads/{$lead->id}")->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson("/api/crm/leads/{$lead->id}", ['name' => 'Updated Lead'])->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson("/api/crm/leads/{$lead->id}/status", ['status' => 'qualified'])->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/crm/leads/{$lead->id}/activities")->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson("/api/crm/leads/{$lead->id}/notes")->assertOk();
    $this->actingAs($admin, 'sanctum')->getJson('/api/crm/lead-stages')->assertOk();
    $this->actingAs($admin, 'sanctum')->postJson('/api/crm/lead-stages', ['name' => 'New Stage', 'key' => 'new-stage'])->assertStatus(201);
    $this->actingAs($admin, 'sanctum')->putJson("/api/crm/lead-stages/{$stage->id}", ['name' => 'Renamed'])->assertOk();
    $this->actingAs($admin, 'sanctum')->putJson("/api/crm/lead-notes/{$note->id}", ['body' => 'Updated'])->assertOk();
});

/**
 * Orders + invoices + payments.
 */
it('billing: orders, invoices, payments full flow', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $invoice = Invoice::factory()->for($user)->create(['status' => 'sent']);
    $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);

    $this->actingAs($user, 'sanctum')->getJson('/api/orders')->assertOk();
    $this->actingAs($user, 'sanctum')->getJson("/api/orders/{$order->id}")->assertOk();
    $this->actingAs($user, 'sanctum')->getJson('/api/invoices')->assertOk();
    $this->actingAs($user, 'sanctum')->getJson("/api/invoices/{$invoice->id}")->assertOk();
    $this->actingAs($user, 'sanctum')->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $this->actingAs($user, 'sanctum')->getJson('/api/payments')->assertOk();
});

/**
 * Tickets.
 */
it('tickets: full lifecycle', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->create(['role' => 'staff']);
    $ticket = Ticket::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer, 'sanctum')->getJson('/api/tickets')->assertOk();
    $this->actingAs($customer, 'sanctum')->getJson("/api/tickets/{$ticket->id}")->assertOk();
    $this->actingAs($customer, 'sanctum')->postJson('/api/tickets', ['subject' => 'S', 'message' => 'M'])->assertStatus(201);
    $this->actingAs($customer, 'sanctum')->postJson("/api/tickets/{$ticket->id}/messages", ['body' => 'Hello'])->assertOk();
    $this->actingAs($customer, 'sanctum')->getJson("/api/tickets/{$ticket->id}/messages")->assertOk();
    $this->actingAs($staff, 'sanctum')->putJson("/api/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertOk();
    $this->actingAs($staff, 'sanctum')->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $staff->id])->assertOk();
});

/**
 * Authorization barriers.
 */
it('authz: customers blocked from admin & staff-only routes', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->create(['role' => 'staff']);

    foreach (['/api/admin/dashboard', '/api/admin/billing/metrics', '/api/admin/licenses'] as $uri) {
        $this->actingAs($customer, 'sanctum')->getJson($uri)->assertForbidden();
    }

    $this->actingAs($customer, 'sanctum')->getJson('/api/admin/downloads')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/admin/downloads/stats')->assertForbidden();
    $this->actingAs($customer, 'sanctum')->getJson('/api/admin/download-logs')->assertForbidden();
});
