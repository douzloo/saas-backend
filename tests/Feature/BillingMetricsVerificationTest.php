<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;

it('billing metrics match raw calculations with invoice items and payments', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $sent = Invoice::factory()->for($user)->create([
        'status' => 'sent', 'subtotal' => 2000, 'discount' => 200, 'tax' => 180,
        'total' => 1980, 'balance_due' => 1980, 'due_at' => now()->addDays(5),
    ]);
    InvoiceItem::factory()->count(2)->create(['invoice_id' => $sent->id, 'product_id' => null]);

    $overdue = Invoice::factory()->for($user)->overdue()->create([
        'subtotal' => 1000, 'discount' => 0, 'tax' => 100,
        'total' => 1100, 'balance_due' => 1100,
    ]);

    $paid = Invoice::factory()->for($user)->paid()->create([
        'subtotal' => 3000, 'discount' => 0, 'tax' => 300,
        'total' => 3300, 'balance_due' => 0, 'paid_at' => now(),
    ]);
    Payment::factory()->create([
        'invoice_id' => $paid->id, 'status' => 'completed', 'amount' => 3300,
        'refunded_amount' => 0, 'payment_type' => 'invoice', 'paid_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/billing/metrics')->assertOk();

    $response->assertJsonPath('invoices.revenue.total', 3300);
    $response->assertJsonPath('invoices.revenue.monthly', 3300);
    $response->assertJsonPath('invoices.invoices.outstanding', 1980 + 1100);
    $response->assertJsonPath('invoices.invoices.overdue', 1100);
    $response->assertJsonPath('invoices.invoices.total_outstanding', 2);
    $response->assertJsonPath('invoices.invoices.total_overdue', 1);
    $response->assertJsonPath('payments.success_rate', 100);
    $response->assertJsonPath('payments.total_received', 3300);
    $response->assertJsonPath('payments.net_revenue', 3300);
});

it('billing metrics handle partial payments and refunds', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $invoice = Invoice::factory()->for($user)->create([
        'status' => 'sent', 'subtotal' => 1000, 'discount' => 0, 'tax' => 100,
        'total' => 1100, 'balance_due' => 1100,
    ]);
    Payment::factory()->create([
        'invoice_id' => $invoice->id, 'status' => 'completed', 'amount' => 600,
        'refunded_amount' => 100, 'payment_type' => 'invoice', 'paid_at' => now(),
    ]);
    Payment::factory()->pending()->create(['invoice_id' => $invoice->id, 'payment_type' => 'invoice']);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/billing/metrics')->assertOk();

    $response->assertJsonPath('payments.success_rate', 50);
    $response->assertJsonPath('payments.total_received', 600);
    $response->assertJsonPath('payments.total_refunded', 100);
    $response->assertJsonPath('payments.net_revenue', 500);
    $response->assertJsonPath('payments.total_transactions', 2);
    $response->assertJsonPath('invoices.revenue.success_rate', 50);
});
