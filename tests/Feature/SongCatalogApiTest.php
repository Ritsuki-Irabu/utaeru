<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SongCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_song_detail_for_playback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $song = Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'lyrics' => "歌い出し\nサビ",
            'bpm' => 92,
        ]);

        $this->getJson('/api/songs/'.$song->id)
            ->assertOk()
            ->assertJsonPath('id', $song->id)
            ->assertJsonPath('title', 'Pretender')
            ->assertJsonPath('lyrics', "歌い出し\nサビ")
            ->assertJsonPath('opening_line', '歌い出し')
            ->assertJsonPath('bpm', 92);
    }

    public function test_user_can_search_local_and_external_catalog_results(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Song::create([
            'title' => 'Pretender',
            'artist' => 'Official髭男dism',
            'bpm' => 92,
        ]);

        Http::fake([
            'https://itunes.apple.com/search*' => Http::response([
                'results' => [
                    [
                        'trackId' => 12345,
                        'trackName' => '新しい曲',
                        'artistName' => '新しいアーティスト',
                        'collectionName' => '新しいアルバム',
                        'trackViewUrl' => 'https://music.apple.com/jp/song/12345',
                        'trackTimeMillis' => 180000,
                        'artworkUrl100' => 'https://example.com/artwork.jpg',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/songs/search?q=Pretender')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pretender')
            ->assertJsonPath('data.0.source', 'catalog')
            ->assertJsonPath('data.1.provider', 'itunes')
            ->assertJsonPath('data.1.provider_key', '12345')
            ->assertJsonPath('data.1.is_imported', false);
    }

    public function test_user_can_search_katakana_artist_with_hiragana_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Song::create([
            'title' => 'ただ君に晴れ',
            'artist' => 'ヨルシカ',
            'bpm' => 120,
        ]);

        Http::fake([
            'https://itunes.apple.com/search*' => Http::response(['results' => []]),
        ]);

        $this->getJson('/api/songs/search?q=よるしか&field=artist')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ただ君に晴れ')
            ->assertJsonPath('data.0.artist', 'ヨルシカ');
    }

    public function test_local_catalog_results_survive_external_provider_failure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Song::create([
            'title' => 'ローカル曲',
            'artist' => 'ローカルアーティスト',
            'bpm' => null,
        ]);

        Http::fake([
            'https://itunes.apple.com/search*' => Http::response([], 503),
        ]);

        $this->getJson('/api/songs/search?q=ローカル曲')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ローカル曲')
            ->assertJsonPath('data.0.source', 'catalog');
    }

    public function test_user_can_import_external_song_without_bpm_and_duplicate_is_reused(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://itunes.apple.com/lookup*' => Http::response([
                'results' => [
                    [
                        'trackId' => 12345,
                        'trackName' => '新しい曲',
                        'artistName' => '新しいアーティスト',
                        'collectionName' => '新しいアルバム',
                        'trackViewUrl' => 'https://music.apple.com/jp/song/12345',
                        'trackTimeMillis' => 180000,
                        'artworkUrl100' => 'https://example.com/artwork.jpg',
                    ],
                ],
            ]),
        ]);

        $first = $this->postJson('/api/songs/import', [
            'provider' => 'itunes',
            'provider_key' => '12345',
        ])->assertCreated()
            ->assertJsonPath('title', '新しい曲')
            ->assertJsonPath('bpm', null);

        $second = $this->postJson('/api/songs/import', [
            'provider' => 'itunes',
            'provider_key' => '12345',
        ])->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('songs', 1);
    }

    public function test_external_song_import_resolves_bpm_from_deezer_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://itunes.apple.com/lookup*' => Http::response([
                'results' => [[
                    'trackId' => 67890,
                    'trackName' => 'BPM確認曲',
                    'artistName' => 'テストアーティスト',
                ]],
            ]),
            'api.deezer.com/search/track*' => Http::response([
                'data' => [[
                    'id' => 24680,
                    'title' => 'BPM確認曲',
                    'artist' => ['name' => 'テストアーティスト'],
                ]],
            ]),
            'api.deezer.com/track/24680' => Http::response([
                'tempo' => 128.4,
                'bpm' => 128.4,
            ]),
        ]);

        $this->postJson('/api/songs/import', [
            'provider' => 'itunes',
            'provider_key' => '67890',
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'BPM確認曲')
            ->assertJsonPath('bpm', 128);
    }

    public function test_external_song_import_matches_deezer_track_by_duration_and_avoids_live_version(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://itunes.apple.com/lookup*' => Http::response([
                'results' => [[
                    'trackId' => 24680,
                    'trackName' => '同じ曲名',
                    'artistName' => '同じアーティスト',
                    'collectionName' => 'アルバム',
                    'trackTimeMillis' => 180000,
                ]],
            ]),
            'api.deezer.com/search/track*' => Http::response([
                'data' => [
                    [
                        'id' => 111,
                        'title' => '同じ曲名 Live',
                        'artist' => ['name' => '同じアーティスト'],
                        'album' => ['title' => 'ライブ盤'],
                        'duration' => 240,
                    ],
                    [
                        'id' => 222,
                        'title' => '同じ曲名',
                        'artist' => ['name' => '同じアーティスト'],
                        'album' => ['title' => 'アルバム'],
                        'duration' => 180,
                    ],
                ],
            ]),
            'api.deezer.com/track/222' => Http::response([
                'tempo' => 121.6,
                'bpm' => 121.6,
            ]),
        ]);

        $this->postJson('/api/songs/import', [
            'provider' => 'itunes',
            'provider_key' => '24680',
        ])
            ->assertCreated()
            ->assertJsonPath('bpm', 122);

        Http::assertNotSent(fn ($request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/track/111'));
    }
}
