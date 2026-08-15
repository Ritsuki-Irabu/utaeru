<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_index_requires_authentication(): void
    {
        $this->getJson('/api/tags')->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_tags(): void
    {
        $user = User::factory()->create();
        Tag::create(['name' => '定番']);
        Tag::create(['name' => '高音注意']);

        Sanctum::actingAs($user);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonPath('data.0.name', '定番')
            ->assertJsonPath('data.1.name', '高音注意');
    }

    public function test_authenticated_user_can_create_a_tag_without_duplicates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/tags', ['name' => '今週練習'])
            ->assertCreated()
            ->assertJsonPath('name', '今週練習');

        $this->postJson('/api/tags', ['name' => '今週練習'])
            ->assertOk()
            ->assertJsonPath('name', '今週練習');

        $this->assertDatabaseCount('tags', 1);
    }
}
