<?php
// tests/Feature/EventsIcsTest.php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_feed_contains_approved_events(): void
    {
        $event = Event::factory()->create([
            'title' => 'Movie Night; Park Edition',
            'starts_at' => now()->addDays(5)->setTime(19, 0),
        ]);
        Event::factory()->create(['title' => 'Hidden Pending', 'status' => 'pending']);

        $response = $this->get('/events.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('SUMMARY:Movie Night\; Park Edition', $content);
        $this->assertStringContainsString('UID:event-' . $event->id . '@lakecitycommons.org', $content);
        $this->assertStringNotContainsString('Hidden Pending', $content);
    }

    public function test_events_beyond_90_days_excluded(): void
    {
        Event::factory()->create(['title' => 'Far Future Gala', 'starts_at' => now()->addDays(200)]);

        $this->assertStringNotContainsString('Far Future Gala', $this->get('/events.ics')->getContent());
    }
}
