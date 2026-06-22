<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログアウト後にトークンが無効化される(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/urls')
            ->assertStatus(401);
    }

    public function test_未認証でログアウトすると401(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
    }
}
