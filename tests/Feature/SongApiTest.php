<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'lyrics' => '権利確認済みの歌詞',
            'bpm' => 120,
        ])->assertCreated()
            ->assertJsonPath('title', 'テスト曲')
            ->assertJsonPath('artist', 'テストアーティスト')
            ->assertJsonPath('lyrics', '権利確認済みの歌詞')
            ->assertJsonPath('bpm', 120);

        $this->assertDatabaseHas('songs', [
            'title' => 'テスト曲',
            'artist' => 'テストアーティスト',
            'lyrics' => '権利確認済みの歌詞',
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

    public function test_admin_role_can_refresh_song_bpm(): void
    {
        $this->actingAdmin();

        $song = Song::create([
            'title' => '再取得曲',
            'artist' => '再取得アーティスト',
            'album' => 'アルバム',
            'duration_ms' => 180000,
            'bpm' => 90,
        ]);

        Http::fake([
            'api.deezer.com/search/track*' => Http::response([
                'data' => [[
                    'id' => 987,
                    'title' => '再取得曲',
                    'artist' => ['name' => '再取得アーティスト'],
                    'album' => ['title' => 'アルバム'],
                    'duration' => 180,
                ]],
            ]),
            'api.deezer.com/track/987' => Http::response(['bpm' => 123.4]),
        ]);

        $this->postJson("/api/songs/{$song->id}/bpm/refresh")
            ->assertOk()
            ->assertJsonPath('bpm', 123);

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'bpm' => 123,
        ]);
    }

    public function test_admin_role_can_refresh_song_bpm_from_spotify_when_deezer_has_no_value(): void
    {
        $this->actingAdmin();
        config([
            'services.spotify.client_id' => 'client-id',
            'services.spotify.client_secret' => 'client-secret',
        ]);

        $song = Song::create([
            'title' => 'Spotify補完曲',
            'artist' => '補完アーティスト',
            'bpm' => null,
        ]);

        Http::fake([
            'api.deezer.com/search/track*' => Http::response([
                'data' => [[
                    'id' => 998,
                    'title' => 'Spotify補完曲',
                    'artist' => ['name' => '補完アーティスト'],
                ]],
            ]),
            'api.deezer.com/track/998' => Http::response(['bpm' => 0]),
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fallback-token',
                'token_type' => 'Bearer',
            ]),
            'api.spotify.com/v1/search*' => Http::response([
                'tracks' => ['items' => [[
                    'id' => 'spotify-fallback-id',
                    'name' => 'Spotify補完曲',
                    'artists' => [['name' => '補完アーティスト']],
                ]]],
            ]),
            'api.spotify.com/v1/audio-features/*' => Http::response(['tempo' => 128.6]),
        ]);

        $this->postJson("/api/songs/{$song->id}/bpm/refresh")
            ->assertOk()
            ->assertJsonPath('bpm', 129);

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'bpm' => 129,
        ]);
    }

    public function test_admin_role_can_refresh_song_bpm_from_getsongbpm_when_other_sources_are_empty(): void
    {
        $this->actingAdmin();
        config(['services.getsongbpm.key' => 'test-key']);

        $song = Song::create([
            'title' => 'GetSongBPM補完曲',
            'artist' => '補完アーティスト',
            'bpm' => null,
        ]);

        Http::fake([
            'api.deezer.com/search/track*' => Http::response(['data' => []]),
            'api.getsong.co/search/*' => Http::response([
                'search' => [[
                    'title' => 'GetSongBPM補完曲',
                    'artist' => ['name' => '補完アーティスト'],
                    'tempo' => 104.7,
                ]],
            ]),
        ]);

        $this->postJson("/api/songs/{$song->id}/bpm/refresh")
            ->assertOk()
            ->assertJsonPath('bpm', 105);
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
