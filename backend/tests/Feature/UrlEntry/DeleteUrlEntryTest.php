<?php

namespace Tests\Feature\UrlEntry;

use App\Models\UrlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUrlEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_自分のURLを論理削除できる(): void
    {
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/urls/{$entry->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('url_entries', [
            'id'     => $entry->id,
            'status' => 'deleted',
        ]);
    }

    public function test_他のユーザのURLは削除できない(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/urls/{$entry->id}")
            ->assertStatus(403);
    }

    public function test_未認証は401(): void
    {
        $entry = UrlEntry::factory()->create();

        $this->deleteJson("/api/v1/urls/{$entry->id}")->assertStatus(401);
    }
}
