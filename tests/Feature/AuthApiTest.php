<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_user_route_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'test@example.com');
    }

    public function test_register_assigns_user_role(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
        ])->assertCreated()
            ->assertJsonStructure([
                'token',
                'user',
            ])
            ->assertJsonPath('user.role', 'user');

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('user'));
    }

    public function test_admin_check_requires_authentication(): void
    {
        $this->getJson('/api/admin/check')
            ->assertUnauthorized();
    }

    public function test_admin_check_forbids_user_role(): void
    {
        Role::firstOrCreate(['name' => 'user']);

        $user = User::factory()->create();
        $user->assignRole('user');

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/check')
            ->assertForbidden();
    }

    public function test_admin_check_allows_admin_role(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/check')
            ->assertOk()
            ->assertJson([
                'message' => 'admin OK',
            ]);
    }
}
