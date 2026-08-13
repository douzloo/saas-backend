<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\User;

it('requires staff to view lead pipeline', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/crm/leads/pipeline')
        ->assertForbidden();
});

it('returns pipeline grouped by stages', function () {
    $staff = User::factory()->staff()->create();
    $stage = LeadStage::factory()->create(['key' => 'new', 'name' => 'جدید', 'sort_order' => 1]);
    Lead::factory()->count(2)->create(['status' => 'new']);
    Lead::factory()->count(1)->create(['status' => 'won']);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/crm/leads/pipeline');

    $response->assertOk()
        ->assertJsonPath('stages.0.name', 'جدید')
        ->assertJsonPath('stages.0.count', 2)
        ->assertJsonCount(2, 'stages.0.leads');
});

it('includes unmapped statuses in pipeline', function () {
    $staff = User::factory()->staff()->create();
    LeadStage::factory()->create(['key' => 'new']);
    Lead::factory()->count(1)->create(['status' => 'lost']);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/crm/leads/pipeline');

    $response->assertOk()->assertJsonPath('unmapped.lost', 1);
});

it('requires staff to list lead stages', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/crm/lead-stages')
        ->assertForbidden();
});

it('lists lead stages ordered', function () {
    $staff = User::factory()->staff()->create();
    LeadStage::factory()->create(['sort_order' => 2]);
    $first = LeadStage::factory()->create(['sort_order' => 1]);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/crm/lead-stages');

    $response->assertOk()->assertJsonPath('data.0.id', $first->id);
});

it('creates a lead stage', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/lead-stages', [
            'name' => 'مذاکره',
            'key' => 'negotiation',
            'color' => '#3B82F6',
            'sort_order' => 4,
        ]);

    $response->assertStatus(201)->assertJsonPath('data.key', 'negotiation');

    $this->assertDatabaseHas('lead_stages', ['key' => 'negotiation']);
});

it('validates duplicate stage key', function () {
    $staff = User::factory()->staff()->create();
    LeadStage::factory()->create(['key' => 'new']);

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/lead-stages', ['name' => 'New', 'key' => 'new']);

    $response->assertStatus(422)->assertJsonValidationErrors('key');
});

it('updates a lead stage', function () {
    $staff = User::factory()->staff()->create();
    $stage = LeadStage::factory()->create(['name' => 'Old']);

    $response = $this->actingAs($staff, 'sanctum')
        ->putJson("/api/crm/lead-stages/{$stage->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
});

it('deletes a lead stage', function () {
    $staff = User::factory()->staff()->create();
    $stage = LeadStage::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/crm/lead-stages/{$stage->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('lead_stages', ['id' => $stage->id]);
});

it('requires staff to create lead notes', function () {
    $lead = Lead::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/notes", ['body' => 'note'])
        ->assertForbidden();
});

it('creates a lead note', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/notes", ['body' => 'یادداشت مهم']);

    $response->assertStatus(201)->assertJsonPath('data.body', 'یادداشت مهم');

    $this->assertDatabaseHas('lead_notes', ['lead_id' => $lead->id, 'body' => 'یادداشت مهم']);
});

it('validates note body is required', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/notes", []);

    $response->assertStatus(422)->assertJsonValidationErrors('body');
});

it('lists lead notes', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create();
    LeadNote::factory()->count(2)->create(['lead_id' => $lead->id]);

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson("/api/crm/leads/{$lead->id}/notes");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('updates a lead note', function () {
    $staff = User::factory()->staff()->create();
    $note = LeadNote::factory()->create(['body' => 'old']);

    $response = $this->actingAs($staff, 'sanctum')
        ->putJson("/api/crm/lead-notes/{$note->id}", ['body' => 'new']);

    $response->assertOk()->assertJsonPath('data.body', 'new');
});

it('deletes a lead note', function () {
    $staff = User::factory()->staff()->create();
    $note = LeadNote::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/crm/lead-notes/{$note->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('lead_notes', ['id' => $note->id]);
});

it('bulk assigns leads', function () {
    $staff = User::factory()->staff()->create();
    $assignee = User::factory()->staff()->create();
    $leads = Lead::factory()->count(3)->create(['assigned_to' => null]);

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/leads/bulk-assign', [
            'lead_ids' => $leads->pluck('id')->all(),
            'assigned_to' => $assignee->id,
        ]);

    $response->assertOk()->assertJsonPath('assigned_count', 3);

    $this->assertDatabaseHas('leads', ['id' => $leads[0]->id, 'assigned_to' => $assignee->id]);
    $this->assertDatabaseHas('lead_activities', ['lead_id' => $leads[0]->id, 'type' => 'system']);
});

it('validates bulk assign requires assignee', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/leads/bulk-assign', ['lead_ids' => []]);

    $response->assertStatus(422);
});

it('requires staff to view reminders', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/crm/leads/reminders')
        ->assertForbidden();
});

it('lists upcoming reminders for staff', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create(['assigned_to' => $staff->id]);
    LeadActivity::factory()->create([
        'lead_id' => $lead->id,
        'type' => 'task',
        'scheduled_at' => now()->addDay(),
        'is_completed' => false,
    ]);
    LeadActivity::factory()->create([
        'lead_id' => $lead->id,
        'type' => 'task',
        'scheduled_at' => now()->subDay(),
        'is_completed' => false,
    ]);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/crm/leads/reminders');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('creates a lead reminder', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/reminders", [
            'subject' => 'پیگیری فروش',
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

    $response->assertStatus(201)->assertJsonPath('data.subject', 'پیگیری فروش');

    $this->assertDatabaseHas('lead_activities', [
        'lead_id' => $lead->id,
        'type' => 'task',
        'subject' => 'پیگیری فروش',
        'is_completed' => false,
    ]);
});

it('validates reminder requires scheduled_at', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/reminders", []);

    $response->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
});

it('completes a reminder', function () {
    $staff = User::factory()->staff()->create();
    $activity = LeadActivity::factory()->create([
        'type' => 'task',
        'is_completed' => false,
        'scheduled_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($staff, 'sanctum')
        ->putJson("/api/crm/reminders/{$activity->id}/complete");

    $response->assertOk()->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('lead_activities', ['id' => $activity->id, 'is_completed' => true]);
});

it('reuses an existing user when converting a lead', function () {
    $staff = User::factory()->staff()->create();
    $existing = User::factory()->create(['email' => 'existing@example.com', 'role' => 'customer']);
    $lead = Lead::factory()->create([
        'email' => 'existing@example.com',
        'status' => 'qualified',
        'assigned_to' => null,
    ]);

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/convert");

    $response->assertOk()->assertJsonPath('user.id', $existing->id);

    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseHas('contacts', ['email' => 'existing@example.com', 'lead_id' => $lead->id]);
});
