<?php
// tests/Feature/FeatureFlagTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'member']);
    }

    public function test_community_routes_return_404_when_flag_off(): void
    {
        config(['features.community' => false]);
        $member = $this->member();

        foreach (['/items', '/skills', '/messages', '/my-requests', '/invite'] as $uri) {
            $this->actingAs($member)->get($uri)->assertNotFound();
        }
    }

    public function test_community_routes_accessible_when_flag_on(): void
    {
        config(['features.community' => true]);
        $member = $this->member();

        $this->actingAs($member)->get('/items')->assertOk();
        $this->actingAs($member)->get('/skills')->assertOk();
    }

    public function test_referral_registration_404_when_flag_off(): void
    {
        config(['features.community' => false]);

        $this->get('/register/any-token-value')->assertNotFound();
    }

    public function test_dashboard_redirects_admin_to_posts_when_flag_off(): void
    {
        config(['features.community' => false]);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->get('/dashboard')
            ->assertRedirect(route('admin.posts.index'));
    }

    public function test_dashboard_404_for_member_when_flag_off(): void
    {
        config(['features.community' => false]);

        $this->actingAs($this->member())->get('/dashboard')->assertNotFound();
    }

    public function test_nav_hides_community_links_when_flag_off(): void
    {
        config(['features.community' => false]);
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $response = $this->actingAs($admin)->get('/profile');
        $response->assertOk();
        $response->assertDontSee('>Skills<', false);
        $response->assertDontSee('>Items<', false);
    }
}
