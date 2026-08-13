<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

it('admin dashboard overview returns valid metrics', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'users' => ['total', 'customers', 'staff', 'new_today'],
            'orders' => ['total', 'completed', 'pending', 'revenue_today', 'revenue_month'],
            'licenses' => ['total', 'active', 'expired'],
            'tickets' => ['total', 'open'],
            'leads' => ['total', 'new', 'won_value'],
            'downloads' => ['total'],
        ]);
});

it('billing metrics returns valid invoice and payment data', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $product = Product::factory()->create();

    Invoice::factory()->for($user)->paid()->create([
        'subtotal' => 1000, 'discount' => 0, 'tax' => 100, 'total' => 1100, 'balance_due' => 0, 'currency' => 'IRR',
    ]);
    Invoice::factory()->for($user)->overdue()->create([
        'subtotal' => 500, 'discount' => 0, 'tax' => 50, 'total' => 550, 'balance_due' => 550, 'currency' => 'IRR',
    ]);

    $paidInvoice = Invoice::where('status', 'paid')->first();
    Payment::factory()->create([
        'invoice_id' => $paidInvoice->id,
        'status' => 'completed',
        'amount' => 1100,
        'refunded_amount' => 0,
        'payment_type' => 'invoice',
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/billing/metrics');

    $response->assertOk()
        ->assertJsonPath('invoices.revenue.total', 1100)
        ->assertJsonPath('invoices.invoices.outstanding', 550)
        ->assertJsonPath('invoices.invoices.total_outstanding', 1)
        ->assertJsonPath('invoices.invoices.total_overdue', 1)
        ->assertJsonPath('payments.success_rate', 100)
        ->assertJsonPath('payments.total_received', 1100)
        ->assertJsonPath('payments.net_revenue', 1100)
        ->assertJsonPath('payments.successful_transactions', 1);
});
