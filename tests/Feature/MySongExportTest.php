<?php

namespace Tests\Feature;

use App\Models\MySong;
use App\Models\Song;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MySongExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_songs_export_requires_authentication(): void
    {
        $this->getJson('/api/my-songs/export')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_download_own_my_songs_csv(): void
    {
        [$user, $otherUser] = [User::factory()->create(), User::factory()->create()];
        $song = Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'bpm' => 92,
        ]);
        $otherSong = Song::create([
            'title' => '他人の曲',
            'artist' => '他人のアーティスト',
            'bpm' => 120,
        ]);
        $tagA = Tag::create(['name' => '高音注意']);
        $tagB = Tag::create(['name' => 'サビ練習']);

        $mySong = MySong::create([
            'user_id' => $user->id,
            'song_id' => $song->id,
            'memo' => 'サビ前を確認',
        ]);
        $mySong->tags()->sync([$tagA->id, $tagB->id]);

        MySong::create([
            'user_id' => $otherUser->id,
            'song_id' => $otherSong->id,
            'memo' => '他人のメモ',
        ]);

        Sanctum::actingAs($user);

        $response = $this->get('/api/my-songs/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('my-songs.csv');

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('曲名,アーティスト,BPM,メモ,タグ,追加日', $csv);
        $this->assertStringContainsString('Pretender,Official髭男dism,92,サビ前を確認,"高音注意, サビ練習"', $csv);
        $this->assertStringNotContainsString('他人の曲', $csv);
        $this->assertStringNotContainsString('他人のメモ', $csv);
    }
}
