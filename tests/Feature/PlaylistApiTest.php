<?php

namespace Tests\Feature;

use App\Models\Playlist;
use App\Models\PlaylistSong;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaylistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_playlist_index_requires_authentication(): void
    {
        $this->getJson('/api/playlists')->assertUnauthorized();
    }

    public function test_user_can_create_and_update_playlist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/playlists', [
            'name' => '飲み会で歌う曲',
            'description' => '最初に歌いやすい曲',
        ])->assertCreated()
            ->assertJsonPath('name', '飲み会で歌う曲')
            ->assertJsonPath('visibility', 'private');

        $playlistId = $response->json('id');

        $this->putJson("/api/playlists/{$playlistId}", [
            'name' => '更新したプレイリスト',
            'description' => '説明を更新',
        ])->assertOk()
            ->assertJsonPath('name', '更新したプレイリスト')
            ->assertJsonPath('description', '説明を更新');
    }

    public function test_playlist_owner_can_add_update_reorder_and_delete_songs(): void
    {
        [$user, $playlist, $firstSong, $secondSong] = $this->createPlaylistContext();
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/playlists/{$playlist->id}/songs", [
            'song_id' => $firstSong->id,
            'memo' => '最初に歌う',
        ])->assertCreated()
            ->assertJsonPath('position', 1)
            ->json('id');

        $second = $this->postJson("/api/playlists/{$playlist->id}/songs", [
            'song_id' => $secondSong->id,
            'position' => 1,
        ])->assertCreated()
            ->assertJsonPath('position', 1)
            ->json('id');

        $this->assertDatabaseHas('playlist_songs', [
            'id' => $first,
            'position' => 2,
        ]);

        $this->putJson("/api/playlists/{$playlist->id}/songs/{$first}", [
            'position' => 1,
            'memo' => '順番を変更',
        ])->assertOk()
            ->assertJsonPath('position', 1)
            ->assertJsonPath('memo', '順番を変更');

        $this->deleteJson("/api/playlists/{$playlist->id}/songs/{$second}")
            ->assertOk();

        $this->assertDatabaseCount('playlist_songs', 1);
        $this->assertDatabaseHas('playlist_songs', [
            'id' => $first,
            'position' => 1,
        ]);
    }

    public function test_same_song_cannot_be_added_twice_to_playlist(): void
    {
        [$user, $playlist, $song] = $this->createPlaylistContext();
        Sanctum::actingAs($user);

        $this->postJson("/api/playlists/{$playlist->id}/songs", [
            'song_id' => $song->id,
        ])->assertCreated();

        $this->postJson("/api/playlists/{$playlist->id}/songs", [
            'song_id' => $song->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_other_user_cannot_edit_or_delete_playlist(): void
    {
        [$owner, $playlist] = $this->createPlaylistContext();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/playlists/{$playlist->id}")->assertForbidden();
        $this->putJson("/api/playlists/{$playlist->id}", [
            'name' => '不正な更新',
        ])->assertForbidden();
        $this->deleteJson("/api/playlists/{$playlist->id}")->assertForbidden();

        $this->assertDatabaseHas('playlists', [
            'id' => $playlist->id,
            'user_id' => $owner->id,
            'name' => $playlist->name,
        ]);
    }

    public function test_share_link_can_be_issued_revoked_and_copied(): void
    {
        [$owner, $playlist, $song] = $this->createPlaylistContext();
        PlaylistSong::create([
            'playlist_id' => $playlist->id,
            'song_id' => $song->id,
            'added_by_user_id' => $owner->id,
            'position' => 1,
            'memo' => '共有メモ',
        ]);
        Sanctum::actingAs($owner);

        $shareResponse = $this->postJson("/api/playlists/{$playlist->id}/share")
            ->assertOk()
            ->assertJsonStructure(['share_url', 'playlist']);
        $shareUrl = $shareResponse->json('share_url');
        $token = basename(parse_url($shareUrl, PHP_URL_PATH));

        $recipient = User::factory()->create();
        Sanctum::actingAs($recipient);

        $this->getJson("/api/shared/playlists/{$token}")
            ->assertOk()
            ->assertJsonPath('name', $playlist->name)
            ->assertJsonPath('songs.0.song.id', $song->id);

        $copyResponse = $this->postJson("/api/shared/playlists/{$token}/copy")
            ->assertCreated()
            ->assertJsonPath('name', $playlist->name . '（コピー）');

        $this->assertDatabaseHas('playlists', [
            'id' => $copyResponse->json('id'),
            'user_id' => $recipient->id,
            'visibility' => 'private',
        ]);
        $this->assertDatabaseHas('playlist_songs', [
            'playlist_id' => $copyResponse->json('id'),
            'song_id' => $song->id,
            'added_by_user_id' => $recipient->id,
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/playlists/{$playlist->id}/share")
            ->assertOk();

        Sanctum::actingAs($recipient);
        $this->getJson("/api/shared/playlists/{$token}")->assertNotFound();
    }

    public function test_shared_viewer_cannot_edit_playlist(): void
    {
        [$owner, $playlist] = $this->createPlaylistContext();
        Sanctum::actingAs($owner);

        $shareResponse = $this->postJson("/api/playlists/{$playlist->id}/share")
            ->assertOk();
        $token = basename(parse_url($shareResponse->json('share_url'), PHP_URL_PATH));

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer);

        $this->putJson("/api/playlists/{$playlist->id}", [
            'name' => '共有先から変更',
        ])->assertForbidden();

        $this->assertNotEmpty($token);
    }

    public function test_playlist_song_id_must_belong_to_route_playlist(): void
    {
        [$user, $playlist] = $this->createPlaylistContext();
        $otherPlaylist = Playlist::create([
            'user_id' => $user->id,
            'name' => '別プレイリスト',
            'visibility' => 'private',
        ]);
        $song = Song::create([
            'title' => '別曲',
            'artist' => '別アーティスト',
            'bpm' => 100,
        ]);
        $playlistSong = PlaylistSong::create([
            'playlist_id' => $otherPlaylist->id,
            'song_id' => $song->id,
            'added_by_user_id' => $user->id,
            'position' => 1,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/playlists/{$playlist->id}/songs/{$playlistSong->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Playlist, 2: Song, 3?: Song}
     */
    private function createPlaylistContext(): array
    {
        $user = User::factory()->create();
        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => '歌う曲リスト',
            'description' => 'テスト用',
            'visibility' => 'private',
        ]);
        $firstSong = Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'bpm' => 92,
        ]);
        $secondSong = Song::create([
            'title' => '残酷な天使のテーゼ',
            'artist' => '高橋洋子',
            'bpm' => 128,
        ]);

        return [$user, $playlist, $firstSong, $secondSong];
    }
}
