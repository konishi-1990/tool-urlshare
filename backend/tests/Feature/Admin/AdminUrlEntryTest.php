<?php

namespace Tests\Feature\Admin;

use App\Models\UrlEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUrlEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_管理者は全ユーザのURLリストを取得できる(): void
    {
        $admin = User::factory()->admin()->create();
        $u1    = User::factory()->create();
        $u2    = User::factory()->create();
        UrlEntry::factory()->count(2)->create(['user_id' => $u1->id]);
        UrlEntry::factory()->count(3)->create(['user_id' => $u2->id]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/urls')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_非管理者は403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/urls')
            ->assertStatus(403);
    }

    public function test_未認証は401(): void
    {
        $this->getJson('/api/v1/admin/urls')->assertStatus(401);
    }

    public function test_管理者は任意のURLを削除できる(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        $entry = UrlEntry::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/urls/{$entry->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('url_entries', ['id' => $entry->id, 'status' => 'deleted']);
    }

    public function test_管理者はブックマーク済みURLをHTMLエクスポートできる(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        UrlEntry::factory()->bookmarked()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->get('/api/v1/admin/export/bookmarks')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
