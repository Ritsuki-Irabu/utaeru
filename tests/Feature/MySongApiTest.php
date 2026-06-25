<?php

namespace Tests\Feature;

use App\Models\MySong;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MySongApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_songs_index_requires_authentication(): void
    {
        $this->getJson('/api/my-songs')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_only_own_my_songs(): void
    {
        [$user, $otherUser, $song] = $this->createUsersAndSong();

        $ownMySong = MySong::create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'memo' => '自分のメモ',
        ]);

        MySong::create([
            'user_id' => $otherUser->id,
            'song_id' => $song->id,
            'memo' => '他人のメモ',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/my-songs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownMySong->id)
            ->assertJsonPath('data.0.memo', '自分のメモ')
            ->assertJsonPath('data.0.song.title', 'Pretender');
    }

    public function test_authenticated_user_can_create_my_song_with_tags(): void
    {
        [$user, , $song] = $this->createUsersAndSong();
        $tagA = Tag::create(['name' => '高音注意']);
        $tagB = Tag::create(['name' => 'サビ練習']);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-songs', [
            'song_id' => $song->id,
            'memo' => 'サビ前を確認する',
            'tag_ids' => [$tagA->id, $tagB->id],
        ])->assertCreated()
            ->assertJsonPath('memo', 'サビ前を確認する')
            ->assertJsonPath('song.id', $song->id)
            ->assertJsonCount(2, 'tags');

        $mySong = MySong::where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('my_songs', [
            'id' => $mySong->id,
            'user_id' => $user->id,
            'song_id' => $song->id,
            'memo' => 'サビ前を確認する',
        ]);

        $this->assertDatabaseHas('my_song_tag', [
            'my_song_id' => $mySong->id,
            'tag_id' => $tagA->id,
        ]);
    }

    public function test_my_song_create_requires_existing_song(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-songs', [
            'song_id' => 999,
            'memo' => '存在しない曲',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['song_id']);
    }

    public function test_owner_can_update_my_song_memo_and_tags(): void
    {
        [$user, , $song] = $this->createUsersAndSong();
        $tagA = Tag::create(['name' => '高音注意']);
        $tagB = Tag::create(['name' => 'サビ練習']);
        $tagC = Tag::create(['name' => '定番']);

        $mySong = MySong::create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'memo' => '更新前',
        ]);
        $mySong->tags()->sync([$tagA->id, $tagB->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/my-songs/{$mySong->id}", [
            'memo' => '更新後',
            'tag_ids' => [$tagB->id, $tagC->id],
        ])->assertOk()
            ->assertJsonPath('memo', '更新後')
            ->assertJsonCount(2, 'tags');

        $this->assertDatabaseHas('my_songs', [
            'id' => $mySong->id,
            'memo' => '更新後',
        ]);
        $this->assertDatabaseMissing('my_song_tag', [
            'my_song_id' => $mySong->id,
            'tag_id' => $tagA->id,
        ]);
        $this->assertDatabaseHas('my_song_tag', [
            'my_song_id' => $mySong->id,
            'tag_id' => $tagC->id,
        ]);
    }

    public function test_user_cannot_update_other_users_my_song(): void
    {
        [$user, $otherUser, $song] = $this->createUsersAndSong();

        $otherMySong = MySong::create([
            'user_id' => $otherUser->id,
            'song_id' => $song->id,
            'memo' => '他人のメモ',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/my-songs/{$otherMySong->id}", [
            'memo' => '変更しようとする',
        ])->assertForbidden();

        $this->assertDatabaseHas('my_songs', [
            'id' => $otherMySong->id,
            'memo' => '他人のメモ',
        ]);
    }

    public function test_owner_can_delete_my_song(): void
    {
        [$user, , $song] = $this->createUsersAndSong();
        $tag = Tag::create(['name' => '定番']);

        $mySong = MySong::create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'memo' => '削除対象',
        ]);
        $mySong->tags()->sync([$tag->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/my-songs/{$mySong->id}")
            ->assertOk()
            ->assertJson([
                'message' => '削除しました。',
            ]);

        $this->assertDatabaseMissing('my_songs', [
            'id' => $mySong->id,
        ]);
        $this->assertDatabaseMissing('my_song_tag', [
            'my_song_id' => $mySong->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_my_song(): void
    {
        [$user, $otherUser, $song] = $this->createUsersAndSong();

        $otherMySong = MySong::create([
            'user_id' => $otherUser->id,
            'song_id' => $song->id,
            'memo' => '他人のメモ',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/my-songs/{$otherMySong->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('my_songs', [
            'id' => $otherMySong->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Song}
     */
    private function createUsersAndSong(): array
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $song = Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'bpm' => 92,
        ]);

        return [$user, $otherUser, $song];
    }
}
