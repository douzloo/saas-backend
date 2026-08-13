<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;

it('allows customers to create draft invoices for themselves only', function () {
    $customer = User::factory()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/invoices', [
            'subtotal' => 1000,
            'items' => [['description' => 'test', 'quantity' => 1, 'unit_price' => 1000]],
        ]);

    expect($response->status())->toBe(201)
        ->and($response->json('data.status'))->toBe('draft')
        ->and($response->json('data.user_id'))->toBe($customer->id);
});

it('forbids customers from creating paid or sent invoices', function () {
    $customer = User::factory()->create();

    foreach (['paid', 'sent', 'issued', 'overdue', 'cancelled'] as $status) {
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/invoices', [
                'status' => $status,
                'items' => [['description' => 'test', 'quantity' => 1, 'unit_price' => 1000]],
            ])
            ->assertStatus(422);
    }
});

it('blocks customers from recording payments', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/payments', ['amount' => 100, 'invoice_id' => 1])
        ->assertForbidden();
});

it('forbids customers from updating, deleting or refunding invoices', function () {
    $customer = User::factory()->create();
    $invoice = Invoice::factory()->for($customer)->create();
    $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);

    $this->actingAs($customer, 'sanctum')
        ->putJson("/api/invoices/{$invoice->id}", ['status' => 'paid'])
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->deleteJson("/api/invoices/{$invoice->id}")
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/payments/{$payment->id}/refund")
        ->assertForbidden();
});

it('forbids customers from replying to another users ticket', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $ticket = Ticket::factory()->for($owner)->create();

    $this->actingAs($attacker, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", ['body' => 'این تیکت مال من نیست'])
        ->assertForbidden();
});

it('forbids customers from changing another users ticket status', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $ticket = Ticket::factory()->for($owner)->create();

    $this->actingAs($attacker, 'sanctum')
        ->putJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertForbidden();
});

it('forbids customers from assigning tickets', function () {
    $customer = User::factory()->create();
    $assignee = User::factory()->create();
    $ticket = Ticket::factory()->for($customer)->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $assignee->id])
        ->assertForbidden();
});
