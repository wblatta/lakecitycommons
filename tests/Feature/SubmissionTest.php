<?php
// tests/Feature/SubmissionTest.php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validEventPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'event',
            'submitter_name' => 'Pat Neighbor',
            'submitter_email' => 'pat@example.com',
            'title' => 'Block Party',
            'body' => 'Annual street gathering.',
            'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'location' => '125th & 28th Ave NE',
        ], $overrides);
    }

    public function test_form_renders(): void
    {
        $this->get('/submit')->assertOk()->assertSee('Submit');
    }

    public function test_valid_event_submission_stored_as_pending(): void
    {
        $this->post('/submit', $this->validEventPayload())->assertRedirect();

        $submission = Submission::first();
        $this->assertEquals('pending', $submission->status);
        $this->assertEquals('event', $submission->type);
        $this->assertEquals('125th & 28th Ave NE', $submission->event_fields['location']);
        $this->assertNotEmpty($submission->ip_hash);
    }

    public function test_announcement_needs_no_event_fields(): void
    {
        $this->post('/submit', [
            'type' => 'announcement',
            'submitter_name' => 'Org Person',
            'submitter_email' => 'org@example.com',
            'title' => 'New Food Bank Hours',
            'body' => 'Now open Saturdays.',
        ])->assertRedirect();

        $this->assertNull(Submission::first()->event_fields);
    }

    public function test_event_submission_requires_future_start(): void
    {
        $this->post('/submit', $this->validEventPayload([
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('starts_at');
    }

    public function test_honeypot_filled_drops_submission_silently(): void
    {
        $this->post('/submit', $this->validEventPayload(['website' => 'http://spam.example']))
            ->assertRedirect();

        $this->assertEquals(0, Submission::count());
    }

    public function test_javascript_scheme_url_rejected(): void
    {
        $this->post('/submit', $this->validEventPayload([
            'url' => 'javascript://x/%0aalert(1)',
        ]))->assertSessionHasErrors('url');
    }

    public function test_rate_limited_after_five_per_day(): void
    {
        RateLimiter::clear('submissions');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/submit', $this->validEventPayload(['title' => "Event {$i}"]));
        }

        $this->post('/submit', $this->validEventPayload(['title' => 'One Too Many']))
            ->assertStatus(429);
    }
}
