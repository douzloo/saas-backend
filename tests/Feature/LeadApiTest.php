<?php

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;

it('requires staff to list leads', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/crm/leads')
        ->assertForbidden();
});

it('lists leads for staff', function () {
    $staff = User::factory()->staff()->create();
    Lead::factory()->count(3)->create(['assigned_to' => $staff->id]);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/crm/leads');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters leads by status', function () {
    $staff = User::factory()->staff()->create();
    Lead::factory()->create(['status' => 'new']);
    Lead::factory()->create(['status' => 'won']);

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson('/api/crm/leads?status=new');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('searches leads', function () {
    $staff = User::factory()->staff()->create();
    Lead::factory()->create(['name' => 'علی رضایی']);
    Lead::factory()->create(['name' => 'مهدی محمدی']);
    Lead::factory()->create(['name' => 'سارا احمدی']);

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson('/api/crm/leads?search=علی');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('creates a lead', function () {
    $staff = User::factory()->staff()->create();
    $tag = Tag::factory()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/leads', [
            'name' => 'شرکت ایران سافت',
            'email' => 'info@iransoft.example',
            'phone' => '09121234567',
            'source' => 'website',
            'priority' => 'high',
            'estimated_value' => 15000000,
            'tags' => [$tag->id],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'شرکت ایران سافت')
        ->assertJsonPath('data.email', 'info@iransoft.example');

    $this->assertDatabaseHas('leads', ['name' => 'شرکت ایران سافت']);
    $this->assertDatabaseHas('taggables', [
        'tag_id' => $tag->id,
        'taggable_type' => Lead::class,
    ]);
});

it('validates lead email format', function () {
    $staff = User::factory()->staff()->create();

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson('/api/crm/leads', [
            'name' => 'Test',
            'email' => 'not-an-email',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

it('shows a lead with relationships', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create(['assigned_to' => $staff->id]);

    $response = $this->actingAs($staff, 'sanctum')
        ->getJson("/api/crm/leads/{$lead->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $lead->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'status', 'assignee', 'tags', 'activities']]);
});

it('updates lead status', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create(['status' => 'new']);

    $response = $this->actingAs($staff, 'sanctum')
        ->putJson("/api/crm/leads/{$lead->id}/status", ['status' => 'qualified']);

    $response->assertOk()->assertJsonPath('data.status', 'qualified');

    $this->assertDatabaseHas('lead_activities', [
        'lead_id' => $lead->id,
        'type' => 'status_change',
    ]);
});

it('adds activity to a lead', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create(['assigned_to' => $staff->id]);

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/activities", [
            'type' => 'call',
            'subject' => 'تماس اولیه',
            'description' => 'مشتری پیگیری شد.',
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('lead_activities', [
        'lead_id' => $lead->id,
        'type' => 'call',
        'subject' => 'تماس اولیه',
    ]);
});

it('converts a lead to customer', function () {
    $staff = User::factory()->staff()->create();
    $lead = Lead::factory()->create([
        'name' => 'مشتری تبدیل‌شده',
        'email' => 'convert@example.com',
        'status' => 'qualified',
    ]);

    $response = $this->actingAs($staff, 'sanctum')
        ->postJson("/api/crm/leads/{$lead->id}/convert");

    $response->assertOk()->assertJsonPath('lead.status', 'won');

    $this->assertDatabaseHas('users', ['email' => 'convert@example.com', 'role' => 'customer']);
    $this->assertDatabaseHas('contacts', ['email' => 'convert@example.com']);
});
