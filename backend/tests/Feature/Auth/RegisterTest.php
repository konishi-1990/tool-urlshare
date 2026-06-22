<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_正常なパラメータでユーザ登録できる(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['token']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_メールアドレスが重複している場合は422(): void
    {
        \App\Models\User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'email'    => 'dup@example.com',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_パスワードが8文字未満の場合は422(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email'    => 'test@example.com',
            'password' => 'short',
        ])->assertStatus(422);
    }

    public function test_emailが不正形式の場合は422(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email'    => 'not-an-email',
            'password' => 'password123',
        ])->assertStatus(422);
    }
}
