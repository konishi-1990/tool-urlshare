<?php

namespace Tests\Feature\UrlEntry;

use App\Models\UrlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUrlEntryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_仮保存をブックマークに変更できる(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'temporary']);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'bookmarked'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'bookmarked');
    }

    public function test_仮保存を削除に変更できる(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'temporary']);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'deleted'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'deleted');
    }

    public function test_ブックマークを削除に変更できる(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id, 'status' => 'bookmarked']);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'deleted'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'deleted');
    }

    public function test_他のユーザのURLは変更できない(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'bookmarked'])
            ->assertStatus(403);
    }

    public function test_削除済みURLはいかなるステータスにも変更できない(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->deleted()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'bookmarked'])
            ->assertStatus(422);
    }

    public function test_bookmarkedからtemporaryへの逆戻りは422(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->bookmarked()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'temporary'])
            ->assertStatus(422);
    }

    public function test_存在しないステータス値は422(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'invalid'])
            ->assertStatus(422);
    }

    public function test_未認証は401(): void
    {
        $entry = UrlEntry::factory()->create();

        $this->patchJson("/api/v1/urls/{$entry->id}", ['status' => 'bookmarked'])
            ->assertStatus(401);
    }
}
