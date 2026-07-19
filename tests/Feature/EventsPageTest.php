<?php
// tests/Feature/EventsPageTest.php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_upcoming_approved_events_only(): void
    {
        Event::factory()->create(['title' => 'Upcoming Cleanup', 'starts_at' => now()->addDays(3)]);
        Event::factory()->create(['title' => 'Past Picnic', 'starts_at' => now()->subDays(3)]);
        Event::factory()->create(['title' => 'Sketchy Pending', 'status' => 'pending', 'starts_at' => now()->addDays(3)]);

        $response = $this->get('/events');

        $response->assertOk();
        $response->assertSee('Upcoming Cleanup');
        $response->assertDontSee('Past Picnic');
        $response->assertDontSee('Sketchy Pending');
    }

    public function test_filter_by_organization_slug(): void
    {
        $org = Organization::factory()->create(['name' => 'Garden Club']);
        Event::factory()->create(['title' => 'Seed Swap', 'organization_id' => $org->id, 'starts_at' => now()->addDays(2)]);
        Event::factory()->create(['title' => 'Unrelated Meetup', 'starts_at' => now()->addDays(2)]);

        $response = $this->get('/events?organization=' . $org->slug);

        $response->assertSee('Seed Swap');
        $response->assertDontSee('Unrelated Meetup');
    }

    public function test_month_view_renders_grid_with_events(): void
    {
        $date = now()->addMonth()->startOfMonth()->addDays(9)->setTime(18, 0);
        Event::factory()->create(['title' => 'Trivia Night', 'starts_at' => $date]);

        $response = $this->get('/events?view=month&month=' . $date->format('Y-m'));

        $response->assertOk();
        $response->assertSee('Trivia Night');
        $response->assertSee($date->format('F Y'));
    }

    public function test_invalid_month_param_falls_back_to_current_month(): void
    {
        $this->get('/events?view=month&month=garbage')->assertOk()->assertSee(now()->format('F Y'));
    }
}
