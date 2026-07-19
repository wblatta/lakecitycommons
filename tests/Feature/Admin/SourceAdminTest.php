<?php

namespace Tests\Feature\Admin;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_admin_can_create_source_with_selector_config(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'LCNA Blog',
            'url' => 'https://example.org/feed.xml',
            'type' => 'rss',
            'selector_config' => '',
            'active' => 1,
        ])->assertRedirect(route('admin.sources.index'));

        $this->assertDatabaseHas('sources', ['name' => 'LCNA Blog', 'type' => 'rss']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'source_create']);
    }

    public function test_invalid_json_selector_config_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'Broken', 'url' => 'https://example.org', 'type' => 'html',
            'selector_config' => '{not json',
        ])->assertSessionHasErrors('selector_config');
    }

    public function test_invalid_type_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/sources', [
            'name' => 'X', 'url' => 'https://example.org', 'type' => 'carrier-pigeon',
        ])->assertSessionHasErrors('type');
    }

    public function test_index_shows_failure_badge(): void
    {
        Source::factory()->create(['name' => 'Flaky Feed', 'consecutive_failures' => 3]);

        $this->actingAs($this->admin())->get('/admin/sources')
            ->assertOk()->assertSee('Flaky Feed')->assertSee('3 failures');
    }

    public function test_admin_can_delete_source(): void
    {
        $source = Source::factory()->create();

        $this->actingAs($this->admin())->delete("/admin/sources/{$source->id}");
        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'source_delete']);
    }

    public function test_non_admin_blocked(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $this->actingAs($member)->get('/admin/sources')->assertForbidden();
    }
}
