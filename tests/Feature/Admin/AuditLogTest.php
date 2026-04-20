<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_log(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        AdminAuditLog::create([
            'admin_id'       => $admin->id,
            'target_user_id' => $member->id,
            'action'         => 'status_change',
            'payload'        => ['before' => 'active', 'after' => 'suspended'],
            'ip_address'     => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get('/admin/audit-log');

        $response->assertOk();
        $response->assertSee('status_change');
    }

    public function test_non_admin_cannot_view_audit_log(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/audit-log')->assertForbidden();
    }
}
