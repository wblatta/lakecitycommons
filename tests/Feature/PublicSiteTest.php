<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Lake City Commons');
    }

    public function test_homepage_renders_for_logged_in_users(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->get('/')->assertOk();
    }

    public function test_public_nav_has_section_links(): void
    {
        $response = $this->get('/');

        $response->assertSee('News');
        $response->assertSee('Events');
        $response->assertSee('Directory');
        $response->assertSee('Submit');
    }

    public function test_homepage_shows_latest_posts_and_upcoming_events(): void
    {
        \App\Models\Post::factory()->published()->create(['title' => 'Fresh Digest']);
        \App\Models\Event::factory()->create(['title' => 'Saturday Market', 'starts_at' => now()->addDays(2)]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Fresh Digest');
        $response->assertSee('Saturday Market');
    }

    public function test_homepage_handles_empty_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Lake City Commons');
    }
}
