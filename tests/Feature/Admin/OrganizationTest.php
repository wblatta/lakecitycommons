<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_admin_can_create_organization(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', [
            'name' => 'Lake City Farmers Market',
            'category' => 'community',
            'description' => 'Weekly farmers market.',
            'website' => 'https://example.org',
            'active' => 1,
        ])->assertRedirect(route('admin.organizations.index'));

        $this->assertDatabaseHas('organizations', [
            'name' => 'Lake City Farmers Market',
            'slug' => 'lake-city-farmers-market',
            'category' => 'community',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'organization_create']);
    }

    public function test_invalid_category_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', [
            'name' => 'X', 'category' => 'nonsense',
        ])->assertSessionHasErrors('category');
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        Organization::factory()->create(['name' => 'Book Club']);
        $b = Organization::factory()->create(['name' => 'Book Club']);

        $this->assertEquals('book-club-2', $b->slug);
    }

    public function test_admin_can_update_and_delete(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/organizations/{$org->id}", [
            'name' => $org->name, 'category' => 'business',
            'description' => $org->description, 'active' => 1,
        ])->assertRedirect(route('admin.organizations.index'));
        $this->assertEquals('business', $org->fresh()->category);

        $this->actingAs($admin)->delete("/admin/organizations/{$org->id}");
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'organization_delete']);
    }

    public function test_non_admin_cannot_access(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/organizations')->assertForbidden();
    }
}
