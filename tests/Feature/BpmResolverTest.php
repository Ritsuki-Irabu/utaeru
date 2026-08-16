<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BpmResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_bpm_prefers_original_recording_over_live_version(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $song = Song::create([
            'title' => '安定曲',
            'artist' => 'アーティスト',
            'bpm' => null,
        ]);

        Http::fake([
            'api.deezer.com/search/track*' => Http::response([
                'data' => [
                    [
                        'id' => 111,
                        'title' => '安定曲 (Live)',
                        'artist' => ['name' => 'アーティスト'],
                        'album' => ['title' => 'Live Album'],
                        'duration' => 180,
                    ],
                    [
                        'id' => 222,
                        'title' => '安定曲',
                        'artist' => ['name' => 'アーティスト'],
                        'album' => ['title' => 'Studio Album'],
                        'duration' => 180,
                    ],
                ],
            ]),
            'api.deezer.com/track/222' => Http::response(['bpm' => 118]),
        ]);

        $this->postJson("/api/songs/{$song->id}/bpm/refresh")
            ->assertOk()
            ->assertJsonPath('bpm', 118);

        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/track/111'));
    }

    public function test_refresh_bpm_can_use_bpm_written_in_youtube_metadata_as_last_fallback(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);
        config([
            'services.youtube.key' => 'test-key',
            'services.spotify.client_id' => null,
            'services.spotify.client_secret' => null,
        ]);

        $song = Song::create([
            'title' => '動画補完曲',
            'artist' => '動画補完アーティスト',
            'bpm' => null,
        ]);

        Http::fake([
            'api.deezer.com/search/track*' => Http::response(['data' => []]),
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [[
                    'id' => ['videoId' => 'bpm-video'],
                    'snippet' => [
                        'title' => '動画補完曲 Official Music Video',
                        'channelTitle' => '公式チャンネル',
                        'description' => 'テンポ: 117 BPM',
                    ],
                ]],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'bpm-video',
                    'status' => ['embeddable' => true, 'uploadStatus' => 'processed', 'privacyStatus' => 'public'],
                ]],
            ]),
        ]);

        $this->postJson("/api/songs/{$song->id}/bpm/refresh")
            ->assertOk()
            ->assertJsonPath('bpm', 117);
    }
}
