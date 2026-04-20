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

    public function test_status_change_is_logged(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($admin)
            ->patch("/admin/members/{$member->id}/status", ['status' => 'suspended']);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'       => $admin->id,
            'target_user_id' => $member->id,
            'action'         => 'status_change',
        ]);

        $log = AdminAuditLog::where('action', 'status_change')->first();
        $this->assertEquals(['before' => 'active', 'after' => 'suspended'], $log->payload);
    }

    public function test_credit_adjustment_is_logged_with_balance_snapshot(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'role' => 'member', 'time_bank_balance' => 5.0]);

        $this->actingAs($admin)
            ->post("/admin/members/{$member->id}/adjust-credits", [
                'amount' => 3.0,
                'note'   => 'Test adjustment',
            ]);

        $log = AdminAuditLog::where('action', 'credit_adjustment')->first();
        $this->assertNotNull($log);
        $this->assertEquals(5.0, $log->payload['balance_before']);
        $this->assertEquals(8.0, $log->payload['balance_after']);
    }

    public function test_credit_adjustment_rejected_outside_100_limit(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        $member = User::factory()->create(['status' => 'active', 'role' => 'member', 'time_bank_balance' => 0.0]);

        $response = $this->actingAs($admin)
            ->post("/admin/members/{$member->id}/adjust-credits", [
                'amount' => 150.0,
                'note'   => 'Too large',
            ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'credit_adjustment']);
    }
}
