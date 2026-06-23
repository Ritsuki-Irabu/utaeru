<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpotifyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spotify_search_requires_authentication(): void
    {
        $this->getJson('/api/songs/spotify?q=Shape%20of%20You')
            ->assertUnauthorized();
    }

    public function test_user_role_cannot_search_spotify(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($user);

        $this->getJson('/api/songs/spotify?q=Shape%20of%20You')
            ->assertForbidden();
    }

    public function test_admin_role_can_search_spotify_and_get_bpm(): void
    {
        $this->actingAdmin();

        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
            ]),
            'api.spotify.com/v1/search*' => Http::response([
                'tracks' => [
                    'items' => [
                        [
                            'id' => 'spotify-track-id',
                            'name' => 'Shape of You',
                            'artists' => [
                                ['name' => 'Ed Sheeran'],
                            ],
                        ],
                    ],
                ],
            ]),
            'api.spotify.com/v1/audio-features/*' => Http::response([
                'tempo' => 95.8,
            ]),
        ]);

        $this->getJson('/api/songs/spotify?q=Shape%20of%20You')
            ->assertOk()
            ->assertJsonPath('0.spotify_id', 'spotify-track-id')
            ->assertJsonPath('0.title', 'Shape of You')
            ->assertJsonPath('0.artist', 'Ed Sheeran')
            ->assertJsonPath('0.bpm', 96);

        Http::assertSentCount(3);
    }

    public function test_spotify_search_requires_query(): void
    {
        $this->actingAdmin();

        $this->getJson('/api/songs/spotify')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_spotify_api_error_returns_bad_gateway(): void
    {
        $this->actingAdmin();

        Http::fake([
            'accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
            ]),
            'api.spotify.com/v1/search*' => Http::response('Active premium subscription required for the owner of the app.', 403),
        ]);

        $this->getJson('/api/songs/spotify?q=Shape%20of%20You')
            ->assertStatus(502)
            ->assertJson([
                'message' => 'Spotify APIとの通信に失敗しました。',
            ]);
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
