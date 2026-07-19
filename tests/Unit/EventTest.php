<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_scope_excludes_pending_and_rejected(): void
    {
        Event::factory()->create(['status' => 'approved']);
        Event::factory()->create(['status' => 'pending']);
        Event::factory()->create(['status' => 'rejected']);

        $this->assertEquals(1, Event::approved()->count());
    }

    public function test_event_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($event->organization->is($org));
        $this->assertTrue($org->events->first()->is($event));
    }
}
