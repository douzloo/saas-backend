<?php

use App\Models\License;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;

it('requires authentication to list tickets', function () {
    $this->getJson('/api/tickets')->assertUnauthorized();
});

it('lists only own tickets for a customer', function () {
    $user = User::factory()->create();
    Ticket::factory()->count(2)->for($user)->create();
    Ticket::factory()->for(User::factory())->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/tickets');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('lists all tickets for staff', function () {
    $staff = User::factory()->staff()->create();
    Ticket::factory()->count(3)->create();

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/tickets');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters tickets by status', function () {
    $user = User::factory()->create();
    Ticket::factory()->for($user)->open()->create();
    Ticket::factory()->for($user)->create(['status' => 'closed']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/tickets?status=open');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('creates a ticket with an initial message', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/tickets', [
            'subject' => 'مشکل در نصب',
            'message' => 'بعد از نصب خطا دریافت می‌کنم.',
            'product_id' => $product->id,
            'priority' => 'high',
            'category' => 'technical',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.subject', 'مشکل در نصب')
        ->assertJsonPath('data.status', 'open');

    $this->assertDatabaseHas('tickets', ['subject' => 'مشکل در نصب']);
    $this->assertDatabaseHas('ticket_messages', ['body' => 'بعد از نصب خطا دریافت می‌کنم.']);
});

it('validates ticket subject is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/tickets', ['message' => 'بدون موضوع']);

    $response->assertStatus(422)->assertJsonValidationErrors('subject');
});

it('shows a ticket with messages', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->withMessage()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonStructure(['data' => ['id', 'subject', 'messages']])
        ->assertJsonCount(1, 'data.messages');
});

it('forbids showing another users ticket', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->for($other)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}");

    $response->assertForbidden();
});

it('adds a reply to a ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/messages", [
            'body' => 'اطلاعات تکمیلی ارسال شد.',
        ]);

    $response->assertOk();

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id' => $ticket->id,
        'body' => 'اطلاعات تکمیلی ارسال شد.',
    ]);
});

it('updates ticket status', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved']);

    $response->assertOk()->assertJsonPath('data.status', 'resolved');
});

it('assigns a ticket to staff', function () {
    $staff = User::factory()->staff()->create();
    $assignee = User::factory()->staff()->create();
    $ticket = Ticket::factory()->for(User::factory())->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $assignee->id]);

    $response->assertOk()->assertJsonPath('data.assigned_to', $assignee->id);
});

it('associates a ticket with the users license', function () {
    $user = User::factory()->create();
    $license = License::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/tickets', [
            'subject' => 'مشکل لایسنس',
            'message' => 'لایسنس فعال نمی‌شود.',
            'license_key' => $license->key,
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('tickets', [
        'subject' => 'مشکل لایسنس',
        'license_id' => $license->id,
    ]);
});
