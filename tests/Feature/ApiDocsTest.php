<?php

use App\Models\User;

it('serves the Scramble UI at /docs/api for staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get('/docs/api')->assertOk();
});

it('serves the OpenAPI JSON document at /docs/api.json for staff', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get('/docs/api.json');

    $response->assertOk()
        ->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version'],
            'paths',
            'components' => ['schemas', 'securitySchemes'],
        ]);
});

it('blocks anonymous and non-staff access to API docs', function () {
    $customer = User::factory()->create();

    $this->get('/docs/api')->assertForbidden();
    $this->actingAs($customer)->get('/docs/api')->assertForbidden();
});
