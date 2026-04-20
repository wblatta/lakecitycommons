<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $member = User::factory()->create(['status' => 'active', 'cross_streets' => 'Oak & Maple']);

        $this->get("/users/{$member->id}")->assertRedirect('/login');
    }

    public function test_authenticated_member_cannot_see_cross_streets_of_another_member(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $member = User::factory()->create(['status' => 'active', 'neighborhood_area' => 'Eastside', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($viewer)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertDontSee('Oak & Maple');
    }

    public function test_owner_can_see_their_own_cross_streets(): void
    {
        $member = User::factory()->create(['status' => 'active', 'neighborhood_area' => 'Eastside', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($member)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertSee('Oak & Maple');
    }

    public function test_admin_can_see_any_cross_streets(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'neighborhood_area' => 'Eastside', 'cross_streets' => 'Oak & Maple']);

        $response = $this->actingAs($admin)->get("/users/{$member->id}");

        $response->assertOk();
        $response->assertSee('Oak & Maple');
    }
}
