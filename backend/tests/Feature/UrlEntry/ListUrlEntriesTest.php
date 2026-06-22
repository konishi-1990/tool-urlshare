<?php

namespace Tests\Feature\UrlEntry;

use App\Models\UrlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListUrlEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_認証済みユーザが自分のURLリストを取得できる(): void
    {
        $user = User::factory()->create();
        UrlEntry::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/urls')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_他のユーザのURLは含まれない(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        UrlEntry::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/urls')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_statusパラメータでフィルタリングできる(): void
    {
        $user = User::factory()->create();
        UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'temporary']);
        UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'bookmarked']);

        $this->actingAs($user)
            ->getJson('/api/v1/urls?status=temporary')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_削除済みURLはデフォルトで含まれない(): void
    {
        $user = User::factory()->create();
        UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'temporary']);
        UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'deleted']);

        $this->actingAs($user)
            ->getJson('/api/v1/urls')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_未認証は401(): void
    {
        $this->getJson('/api/v1/urls')->assertStatus(401);
    }
}
