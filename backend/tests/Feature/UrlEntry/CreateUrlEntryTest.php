<?php

namespace Tests\Feature\UrlEntry;

use App\Models\UrlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUrlEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_URLを保存できる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/urls', [
                'url'    => 'https://example.com/article',
                'status' => 'temporary',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['id', 'url', 'status', 'created_at']);

        $this->assertDatabaseHas('url_entries', [
            'user_id' => $user->id,
            'url'     => 'https://example.com/article',
        ]);
    }

    public function test_statusのデフォルトはtemporary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/urls', ['url' => 'https://example.com/'])
            ->assertStatus(201)
            ->assertJsonPath('status', 'temporary');
    }

    public function test_不正なURLフォーマットは422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/urls', ['url' => 'not-a-url'])
            ->assertStatus(422);
    }

    public function test_同じURLの重複保存は409(): void
    {
        $user = User::factory()->create();
        UrlEntry::factory()->create(['user_id' => $user->id, 'url' => 'https://dup.com/']);

        $this->actingAs($user)
            ->postJson('/api/v1/urls', ['url' => 'https://dup.com/'])
            ->assertStatus(409);
    }

    public function test_未認証は401(): void
    {
        $this->postJson('/api/v1/urls', ['url' => 'https://example.com/'])
            ->assertStatus(401);
    }
}
