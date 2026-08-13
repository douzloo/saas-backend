<?php

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('adds a reply with attachments', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'پیوست ضمیمه شد.',
            'attachments' => [
                UploadedFile::fake()->create('report.pdf', 100),
                UploadedFile::fake()->image('screenshot.png'),
            ],
        ]);

    $response->assertOk()->assertJsonCount(2, 'data.messages.0.attachments');

    $this->assertDatabaseCount('ticket_attachments', 2);
});

it('includes attachments in message list', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'با پیوست',
            'attachments' => [
                UploadedFile::fake()->create('file.zip', 50),
            ],
        ]);

    $list = $this->actingAs($user, 'sanctum')
        ->getJson("/api/tickets/{$ticket->id}/messages");

    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.attachments');
});

it('allows the ticket owner to download an attachment', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();
    $message = $ticket->messages()->create([
        'user_id' => $user->id,
        'body' => 'پیام تست',
    ]);

    $attachment = TicketAttachment::create([
        'ticket_message_id' => $message->id,
        'filename' => 'file.txt',
        'original_filename' => 'file.txt',
        'mime_type' => 'text/plain',
        'file_size' => 10,
        'disk' => 'public',
        'path' => 'ticket-attachments/file.txt',
    ]);

    Storage::disk('public')->put('ticket-attachments/file.txt', 'hello world');

    $response = $this->actingAs($user, 'sanctum')
        ->get("/api/ticket-attachments/{$attachment->id}/download");

    $response->assertOk();
});

it('forbids downloading another users attachment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->for($other)->create();
    $message = $ticket->messages()->create([
        'user_id' => $other->id,
        'body' => 'پیام تست',
    ]);

    $attachment = TicketAttachment::create([
        'ticket_message_id' => $message->id,
        'filename' => 'file.txt',
        'original_filename' => 'file.txt',
        'mime_type' => 'text/plain',
        'file_size' => 10,
        'disk' => 'public',
        'path' => 'ticket-attachments/file.txt',
    ]);

    Storage::disk('public')->put('ticket-attachments/file.txt', 'hello world');

    $response = $this->actingAs($user, 'sanctum')
        ->get("/api/ticket-attachments/{$attachment->id}/download");

    $response->assertForbidden();
});

it('validates attachment file size', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->post("/api/tickets/{$ticket->id}/messages", [
            'body' => 'فایل بزرگ',
            'attachments' => [
                UploadedFile::fake()->create('huge.bin', 11000),
            ],
        ]);

    $response->assertStatus(422);
});
