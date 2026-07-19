<?php
// tests/Feature/Admin/ReviewQueueTest.php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_queue_lists_pending_items(): void
    {
        Submission::factory()->create(['title' => 'Pending Announcement']);
        Submission::factory()->create(['title' => 'Old Approved', 'status' => 'approved']);
        Event::factory()->create(['title' => 'Pending Scraped Event', 'status' => 'pending']);

        $response = $this->actingAs($this->admin())->get('/admin/review');

        $response->assertOk();
        $response->assertSee('Pending Announcement');
        $response->assertSee('Pending Scraped Event');
        $response->assertDontSee('Old Approved');
    }

    public function test_approving_event_submission_creates_approved_event(): void
    {
        $submission = Submission::factory()->event()->create(['title' => 'Block Party']);

        $this->actingAs($this->admin())
            ->post("/admin/review/submissions/{$submission->id}/approve")
            ->assertRedirect();

        $this->assertEquals('approved', $submission->fresh()->status);
        $this->assertDatabaseHas('events', [
            'title' => 'Block Party',
            'status' => 'approved',
            'submission_id' => $submission->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'submission_approve']);
    }

    public function test_approving_announcement_does_not_create_event(): void
    {
        $submission = Submission::factory()->create();

        $this->actingAs($this->admin())->post("/admin/review/submissions/{$submission->id}/approve");

        $this->assertEquals('approved', $submission->fresh()->status);
        $this->assertEquals(0, Event::count());
    }

    public function test_rejecting_submission(): void
    {
        $submission = Submission::factory()->create();

        $this->actingAs($this->admin())->post("/admin/review/submissions/{$submission->id}/reject");

        $this->assertEquals('rejected', $submission->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'submission_reject']);
    }

    public function test_approve_and_reject_pending_event(): void
    {
        $event = Event::factory()->create(['status' => 'pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/review/events/{$event->id}/approve");
        $this->assertEquals('approved', $event->fresh()->status);

        $event2 = Event::factory()->create(['status' => 'pending']);
        $this->actingAs($admin)->post("/admin/review/events/{$event2->id}/reject");
        $this->assertEquals('rejected', $event2->fresh()->status);
    }

    public function test_already_handled_submission_404s(): void
    {
        $submission = Submission::factory()->create(['status' => 'approved']);

        $this->actingAs($this->admin())
            ->post("/admin/review/submissions/{$submission->id}/approve")
            ->assertNotFound();
    }

    public function test_non_admin_blocked(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);

        $this->actingAs($member)->get('/admin/review')->assertForbidden();
    }
}
