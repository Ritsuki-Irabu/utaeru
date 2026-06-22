<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SongApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_songs_index_requires_authentication(): void
    {
        $this->getJson('/api/songs')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_songs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'bpm' => 92,
        ]);

        $this->getJson('/api/songs')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pretender')
            ->assertJsonPath('data.0.artist', 'Official髭男dism')
            ->assertJsonPath('data.0.bpm', 92);
    }

    public function test_user_role_cannot_create_song(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($user);

        $this->postJson('/api/songs', [
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 120,
        ])->assertForbidden();

        $this->assertDatabaseMissing('songs', [
            'title' => 'テスト曲',
        ]);
    }

    public function test_admin_role_can_create_song(): void
    {
        $admin = $this->actingAdmin();

        $this->postJson('/api/songs', [
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 120,
        ])->assertCreated()
            ->assertJsonPath('title', 'テスト曲')
            ->assertJsonPath('artist', 'テストアーティスト')
            ->assertJsonPath('bpm', 120);

        $this->assertDatabaseHas('songs', [
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 120,
        ]);

        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_admin_role_can_update_song(): void
    {
        $this->actingAdmin();

        $song = Song::create([
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 120,
        ]);

        $this->putJson("/api/songs/{$song->id}", [
            'title' => 'テスト曲 更新',
            'artist' => 'テストアーティスト',
            'bpm' => 125,
        ])->assertOk()
            ->assertJsonPath('title', 'テスト曲 更新')
            ->assertJsonPath('bpm', 125);

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'title' => 'テスト曲 更新',
            'bpm' => 125,
        ]);
    }

    public function test_admin_role_can_delete_song(): void
    {
        $this->actingAdmin();

        $song = Song::create([
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 120,
        ]);

        $this->deleteJson("/api/songs/{$song->id}")
            ->assertOk()
            ->assertJson([
                'message' => '削除しました。',
            ]);

        $this->assertDatabaseMissing('songs', [
            'id' => $song->id,
        ]);
    }

    public function test_admin_role_cannot_create_song_with_invalid_bpm(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/songs', [
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'bpm' => 301,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bpm']);
    }

    private function actingAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        return $admin;
    }
}
