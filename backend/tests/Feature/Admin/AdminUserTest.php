<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    private function userHeaders(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    // ── GET /admin/users ──

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();
        $headers = $this->adminHeaders();

        $response = $this->getJson('/api/v1/admin/users', $headers);

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [['id', 'email', 'is_admin', 'created_at']]]);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $response = $this->getJson('/api/v1/admin/users', $this->userHeaders());
        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);
    }

    // ── POST /admin/users ──

    public function test_admin_can_create_user(): void
    {
        $headers = $this->adminHeaders();

        $response = $this->postJson('/api/v1/admin/users', [
            'email'    => 'newuser@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ], $headers);

        $response->assertStatus(201)
                 ->assertJsonPath('email', 'newuser@example.com')
                 ->assertJsonPath('is_admin', false);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_admin_can_create_admin_user(): void
    {
        $headers = $this->adminHeaders();

        $response = $this->postJson('/api/v1/admin/users', [
            'email'    => 'newadmin@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ], $headers);

        $response->assertStatus(201)
                 ->assertJsonPath('is_admin', true);
    }

    public function test_create_user_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'exists@example.com']);
        $headers = $this->adminHeaders();

        $response = $this->postJson('/api/v1/admin/users', [
            'email'    => 'exists@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_validates_password_length(): void
    {
        $headers = $this->adminHeaders();

        $response = $this->postJson('/api/v1/admin/users', [
            'email'    => 'valid@example.com',
            'password' => 'short',
            'is_admin' => false,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $response = $this->postJson('/api/v1/admin/users', [
            'email'    => 'new@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ], $this->userHeaders());

        $response->assertStatus(403);
    }

    // ── DELETE /admin/users/{user} ──

    public function test_admin_can_delete_user(): void
    {
        $target  = User::factory()->create();
        $headers = $this->adminHeaders();

        $response = $this->deleteJson("/api/v1/admin/users/{$target->id}", [], $headers);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin   = User::factory()->admin()->create();
        $token   = $admin->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $response = $this->deleteJson("/api/v1/admin/users/{$admin->id}", [], $headers);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $target = User::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/users/{$target->id}", [], $this->userHeaders());

        $response->assertStatus(403);
    }
}
