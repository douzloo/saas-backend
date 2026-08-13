<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;

it('lists the authenticated users invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->count(2)->for($user)->create();
    Invoice::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/invoices');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('filters invoices by status', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->paid()->create();
    Invoice::factory()->for($user)->draft()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/invoices?status=paid');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'paid');
});

it('allows staff to see all invoices', function () {
    $admin = User::factory()->admin()->create();
    Invoice::factory()->count(2)->for(User::factory())->create();

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/invoices');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('creates an invoice with items', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/invoices', [
            'currency' => 'IRR',
            'due_at' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Hosting Plan', 'quantity' => 2, 'unit_price' => 500000, 'tax_rate' => 10],
                ['description' => 'Domain', 'quantity' => 1, 'unit_price' => 200000, 'tax_rate' => 0],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.currency', 'IRR')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(2, 'data.items');

    $invoice = Invoice::first();

    $this->assertNotNull($invoice);
    $this->assertSame(1200000.0, (float) $invoice->subtotal);
    $this->assertSame(100000.0, (float) $invoice->tax);
    $this->assertSame(1300000.0, (float) $invoice->total);
    $this->assertStringStartsWith('INV-', $invoice->invoice_number);
});

it('validates invoice item descriptions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/invoices', [
            'items' => [['quantity' => 1]],
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('items.0.description');
});

it('shows an invoice to its owner', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonCount(2, 'data.items');
});

it('forbids showing another users invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/invoices/{$invoice->id}");

    $response->assertForbidden();
});

it('updates an invoice as staff', function () {
    $admin = User::factory()->admin()->create();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}", [
            'discount' => 100,
            'items' => [
                ['description' => 'Updated Item', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.discount', '100.00')
        ->assertJsonCount(1, 'data.items');

    $this->assertSame(900.0, (float) $invoice->fresh()->total);
});

it('forbids non-staff from updating invoices', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}", ['notes' => 'hi']);

    $response->assertForbidden();
});

it('deletes an invoice as staff', function () {
    $admin = User::factory()->admin()->create();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/invoices/{$invoice->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
});

it('pays an invoice in full', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create([
        'status' => 'sent',
        'subtotal' => 1000000,
        'total' => 1100000,
        'balance_due' => 1100000,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/pay");

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'completed');

    $invoice->refresh();

    $this->assertTrue($invoice->isPaid());
    $this->assertSame(0.0, (float) $invoice->balance_due);
    $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'status' => 'completed']);
});

it('rejects paying an already paid invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->paid()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/pay");

    $response->assertStatus(422);
});

it('rejects paying a cancelled invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->cancelled()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/pay");

    $response->assertStatus(422);
});

it('records a partial payment', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create([
        'status' => 'sent',
        'subtotal' => 1000000,
        'total' => 1100000,
        'balance_due' => 1100000,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/invoices/{$invoice->id}/pay", ['amount' => 500000]);

    $response->assertStatus(201);

    $invoice->refresh();

    $this->assertFalse($invoice->isPaid());
    $this->assertSame(600000.0, (float) $invoice->balance_due);
});

it('downloads an invoice pdf', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/invoices/{$invoice->id}/pdf");

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', "attachment; filename=invoice-{$invoice->invoice_number}.pdf");
});

it('lists payments for the authenticated user', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    Payment::factory()->count(2)->create(['invoice_id' => $invoice->id]);
    Payment::factory()->for(Invoice::factory()->for(User::factory()))->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/payments');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('allows staff to record a payment on an invoice', function () {
    $admin = User::factory()->admin()->create();
    $invoice = Invoice::factory()->create(['balance_due' => 1100000]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 1100000,
            'gateway' => 'manual',
        ]);

    $response->assertStatus(201)->assertJsonPath('data.status', 'completed');

    $this->assertTrue($invoice->fresh()->isPaid());
});

it('forbids customers from recording payments', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/payments', ['invoice_id' => 1, 'amount' => 100]);

    $response->assertForbidden();
});

it('shows a payment to its invoice owner', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/payments/{$payment->id}");

    $response->assertOk()->assertJsonPath('data.id', $payment->id);
});

it('forbids showing another users payment', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/payments/{$payment->id}");

    $response->assertForbidden();
});

it('refunds a payment as staff', function () {
    $admin = User::factory()->admin()->create();
    $invoice = Invoice::factory()->paid()->create(['balance_due' => 0]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 1000000,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/refund");

    $response->assertOk()->assertJsonPath('data.status', 'refunded');

    $this->assertSame(1000000.0, (float) $payment->fresh()->refunded_amount);
    $this->assertSame(1000000.0, (float) $invoice->fresh()->balance_due);
});

it('rejects refunding more than the paid amount', function () {
    $admin = User::factory()->admin()->create();
    $payment = Payment::factory()->create(['amount' => 1000000, 'status' => 'completed']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/refund", ['amount' => 2000000]);

    $response->assertStatus(422);
});

it('forbids customers from refunding payments', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/refund");

    $response->assertForbidden();
});

it('returns billing metrics for admin', function () {
    $admin = User::factory()->admin()->create();

    $user = User::factory()->create();
    $paidInvoice = Invoice::factory()->for($user)->paid()->create(['total' => 2000000, 'balance_due' => 0]);
    Payment::factory()->create(['invoice_id' => $paidInvoice->id, 'amount' => 2000000, 'status' => 'completed']);
    Invoice::factory()->for($user)->create(['status' => 'sent', 'balance_due' => 500000]);
    Invoice::factory()->for($user)->overdue()->create(['balance_due' => 300000]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/billing/metrics');

    $response->assertOk()
        ->assertJsonPath('invoices.revenue.total', 2000000)
        ->assertJsonPath('invoices.revenue.monthly', 2000000)
        ->assertJsonPath('invoices.invoices.outstanding', 800000)
        ->assertJsonPath('invoices.invoices.overdue', 300000)
        ->assertJsonPath('invoices.revenue.success_rate', 100);
});

it('requires admin for billing metrics', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/admin/billing/metrics');

    $response->assertForbidden();
});

it('lists customer billing portal invoices with payments', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    Payment::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/portal/invoices');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('shows portal invoice details with payment history', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/portal/invoices/{$invoice->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonCount(1, 'data.payments');
});
