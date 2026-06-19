<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_user_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_user_route_returns_authenticated_user(): void
    {
        $user = User::factory()->make([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'test@example.com');
    }
}
