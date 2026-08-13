<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a customer', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'علی',
        'email' => 'ali@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['user', 'token'])
        ->assertJsonPath('user.email', 'ali@example.com');

    $this->assertDatabaseHas('users', ['email' => 'ali@example.com', 'role' => 'customer']);
});

it('logs in and returns a token', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user', 'token'])
        ->assertJsonPath('user.id', $user->id);
});

it('rejects login with wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects login for inactive user', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'status' => 'inactive',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/user');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

it('updates the user profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/profile', [
            'name' => 'نام جدید',
            'phone' => '09123456789',
            'city' => 'تهران',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'نام جدید')
        ->assertJsonPath('data.phone', '09123456789');
});

it('changes the user password', function () {
    $user = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertOk();

    $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
});

it('rejects password change with wrong current password', function () {
    $user = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('current_password');
});

it('logs out and deletes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/auth/logout');

    $response->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
