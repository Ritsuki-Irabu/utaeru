<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YoutubeVideoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_embeddable_youtube_videos(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config([
            'services.youtube.key' => 'test-key',
            'services.youtube.region' => 'JP',
            'services.youtube.max_results' => 5,
        ]);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [[
                    'id' => ['kind' => 'youtube#video', 'videoId' => 'abc123'],
                    'snippet' => [
                        'title' => 'Pretender Official Video',
                        'channelTitle' => '公式チャンネル',
                        'description' => 'BPM 128で紹介されている公式動画',
                        'thumbnails' => [
                            'medium' => ['url' => 'https://img.youtube.com/vi/abc123/mqdefault.jpg'],
                        ],
                    ],
                ]],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'abc123',
                    'status' => [
                        'embeddable' => true,
                        'uploadStatus' => 'processed',
                        'privacyStatus' => 'public',
                    ],
                ]],
            ]),
        ]);

        $this->getJson('/api/songs/youtube/search?q=Pretender+Official+MV')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'youtube')
            ->assertJsonPath('data.0.video_id', 'abc123')
            ->assertJsonPath('data.0.bpm', 128)
            ->assertJsonPath('data.0.is_embeddable', true);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['type'] ?? null) === 'video'
                && ($query['videoEmbeddable'] ?? null) === 'true'
                && ($query['videoSyndicated'] ?? null) === 'true'
                && ($query['videoCategoryId'] ?? null) === '10'
                && ($query['key'] ?? null) === 'test-key';
        });

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/videos')
                && ($query['part'] ?? null) === 'status'
                && ($query['id'] ?? null) === 'abc123'
                && ($query['key'] ?? null) === 'test-key';
        });
    }

    public function test_youtube_search_requires_a_query(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/songs/youtube/search?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_youtube_search_prioritizes_official_music_video_over_live_recordings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    [
                        'id' => ['videoId' => 'live123'],
                        'snippet' => [
                            'title' => '曲名 Live performance 2025',
                            'channelTitle' => 'アーティスト公式',
                        ],
                    ],
                    [
                        'id' => ['videoId' => 'mv123'],
                        'snippet' => [
                            'title' => '曲名 Official Music Video',
                            'channelTitle' => 'アーティスト公式',
                        ],
                    ],
                    [
                        'id' => ['videoId' => 'short123'],
                        'snippet' => [
                            'title' => '曲名 #shorts',
                            'channelTitle' => 'アーティスト公式',
                        ],
                    ],
                ],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    ['id' => 'live123', 'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public']],
                    ['id' => 'mv123', 'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public']],
                    ['id' => 'short123', 'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public']],
                ],
            ]),
        ]);

        $this->getJson('/api/songs/youtube/search?q=曲名+official+music+video')
            ->assertOk()
            ->assertJsonPath('data.0.video_id', 'mv123');
    }

    public function test_youtube_search_falls_back_when_music_category_has_no_result(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::sequence()
                ->push(['items' => []])
                ->push([
                    'items' => [[
                        'id' => ['videoId' => 'fallback123'],
                        'snippet' => [
                            'title' => '曲名 Official Video',
                            'channelTitle' => '公式チャンネル',
                        ],
                    ]],
                ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'fallback123',
                    'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public'],
                ]],
            ]),
        ]);

        $this->getJson('/api/songs/youtube/search?q=曲名+公式MV')
            ->assertOk()
            ->assertJsonPath('data.0.video_id', 'fallback123');

        Http::assertSentCount(3);
    }

    public function test_youtube_search_prioritizes_exact_song_title_over_same_artist_other_song(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    [
                        'id' => ['videoId' => 'other-song'],
                        'snippet' => [
                            'title' => 'back number - ブルーアンバー',
                            'channelTitle' => 'back number',
                        ],
                    ],
                    [
                        'id' => ['videoId' => 'exact-song'],
                        'snippet' => [
                            'title' => 'back number - 高嶺の花子さん',
                            'channelTitle' => 'UNIVERSAL MUSIC JAPAN',
                        ],
                    ],
                ],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    ['id' => 'other-song', 'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public']],
                    ['id' => 'exact-song', 'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public']],
                ],
            ]),
        ]);

        $this->getJson('/api/songs/youtube/search?q=%22%E9%AB%98%E5%B6%BA%E3%81%AE%E8%8A%B1%E5%AD%90%E3%81%95%E3%82%93%22+%22back+number%22+official+music+video')
            ->assertOk()
            ->assertJsonPath('data.0.video_id', 'exact-song');
    }

    public function test_song_id_search_builds_youtube_query_from_song_master(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        $song = Song::create([
            'title' => '高嶺の花子さん',
            'artist' => 'back number',
        ]);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [[
                    'id' => ['videoId' => 'linked-song'],
                    'snippet' => [
                        'title' => 'back number - 高嶺の花子さん Official Music Video',
                        'channelTitle' => 'UNIVERSAL MUSIC JAPAN',
                    ],
                ]],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'linked-song',
                    'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public'],
                ]],
            ]),
        ]);

        $this->getJson("/api/songs/youtube/search?song_id={$song->id}")
            ->assertOk()
            ->assertJsonPath('data.0.video_id', 'linked-song');

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['q'] ?? null) === '"高嶺の花子さん" "back number" official music video';
        });
    }

    public function test_song_title_mismatch_does_not_return_another_song(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        $song = Song::create([
            'title' => '探している曲',
            'artist' => 'アーティスト',
        ]);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [[
                    'id' => ['videoId' => 'wrong-song'],
                    'snippet' => [
                        'title' => 'アーティスト - 別の曲 Official Music Video',
                        'channelTitle' => 'アーティスト公式',
                    ],
                ]],
            ]),
        ]);

        $this->getJson("/api/songs/youtube/search?song_id={$song->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Http::assertSentCount(1);
    }

    public function test_youtube_search_returns_bad_gateway_when_api_key_is_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => null]);

        $this->getJson('/api/songs/youtube/search?q=Pretender')
            ->assertStatus(503)
            ->assertJsonPath('message', 'YouTube APIキーが未設定です。.envのYOUTUBE_API_KEYを設定してください。');
    }

    public function test_youtube_search_returns_bad_gateway_when_youtube_api_fails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['services.youtube.key' => 'test-key']);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response(['error' => ['message' => 'temporary failure']], 503),
        ]);

        $this->getJson('/api/songs/youtube/search?q=Pretender')
            ->assertStatus(502)
            ->assertJsonPath('message', 'YouTube APIへの接続に失敗しました。');
    }
}
