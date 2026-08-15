<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LyricsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_lyrics_are_returned_without_calling_external_service(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.lyrics.url' => 'https://licensed.example.test/lyrics']);
        Http::fake();

        $song = Song::create([
            'title' => '登録曲',
            'artist' => '登録アーティスト',
            'lyrics' => "歌い出し\nサビ",
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertOk()
            ->assertJsonPath('data.lyrics', "歌い出し\nサビ")
            ->assertJsonPath('data.source', 'registered');

        Http::assertNothingSent();
    }

    public function test_song_without_registered_lyrics_can_use_configured_licensed_provider(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config([
            'services.lyrics.url' => 'https://licensed.example.test/lyrics',
            'services.lyrics.token' => 'licensed-token',
        ]);
        Http::fake([
            'https://licensed.example.test/lyrics*' => Http::response([
                'lyrics' => "提供元の歌詞\nサビ",
                'source_url' => 'https://licensed.example.test/songs/1',
            ]),
        ]);

        $song = Song::create([
            'title' => '未登録曲',
            'artist' => '未登録アーティスト',
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertOk()
            ->assertJsonPath('data.lyrics', "提供元の歌詞\nサビ")
            ->assertJsonPath('data.opening_line', '提供元の歌詞')
            ->assertJsonPath('data.source_url', 'https://licensed.example.test/songs/1')
            ->assertJsonPath('data.source', 'licensed_provider');

        $this->assertDatabaseHas('songs', [
            'id' => $song->id,
            'lyrics' => "提供元の歌詞\nサビ",
        ]);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer licensed-token')
                && $request->data()['title'] === '未登録曲'
                && $request->data()['artist'] === '未登録アーティスト';
        });
    }

    public function test_song_lyrics_endpoint_returns_not_found_when_lrclib_has_no_match(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.lyrics.url' => 'https://lrclib.net/api/get']);
        Http::fake([
            'https://lrclib.net/api/get*' => Http::response([], 404),
            'https://lrclib.net/api/search*' => Http::response([], 404),
        ]);

        $song = Song::create([
            'title' => '未登録曲',
            'artist' => '未登録アーティスト',
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_default_lrclib_response_is_normalized_to_lyrics_card_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.lyrics.url' => 'https://lrclib.net/api/get']);
        Http::fake([
            'https://lrclib.net/api/get*' => Http::response([
                'trackName' => 'LRCLIB曲',
                'artistName' => 'LRCLIBアーティスト',
                'plainLyrics' => "歌い出し\nサビ",
                'syncedLyrics' => null,
            ]),
        ]);

        $song = Song::create([
            'title' => 'LRCLIB曲',
            'artist' => 'LRCLIBアーティスト',
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertOk()
            ->assertJsonPath('data.lyrics', "歌い出し\nサビ")
            ->assertJsonPath('data.opening_line', '歌い出し')
            ->assertJsonPath('data.source_url', 'https://lrclib.net/');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['track_name'] ?? null) === 'LRCLIB曲'
                && ($query['artist_name'] ?? null) === 'LRCLIBアーティスト';
        });
    }

    public function test_lrclib_search_fallback_handles_title_or_artist_variations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.lyrics.url' => 'https://lrclib.net/api/get']);
        Http::fake([
            'https://lrclib.net/api/get*' => Http::response([], 404),
            'https://lrclib.net/api/search*' => Http::response([
                [
                    'trackName' => 'Pretender (Official髭男dism)',
                    'artistName' => 'Official髭男dism',
                    'plainLyrics' => "君とのラブストーリー\nサビ",
                ],
            ]),
        ]);

        $song = Song::create([
            'title' => 'Pretender',
            'artist' => '髭男',
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertOk()
            ->assertJsonPath('data.lyrics', "君とのラブストーリー\nサビ")
            ->assertJsonPath('data.source_url', 'https://lrclib.net/');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'lrclib.net/api/search')
                && str_contains((string) ($request->data()['q'] ?? ''), 'Pretender');
        });
    }

    public function test_song_lyrics_endpoint_returns_bad_gateway_when_provider_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.lyrics.url' => 'https://licensed.example.test/lyrics']);
        Http::fake([
            'https://licensed.example.test/lyrics*' => Http::response([], 503),
        ]);

        $song = Song::create([
            'title' => '未登録曲',
            'artist' => '未登録アーティスト',
        ]);

        $this->getJson("/api/songs/{$song->id}/lyrics")
            ->assertStatus(502)
            ->assertJsonPath('message', '歌詞サービスに接続できませんでした。');
    }
}
