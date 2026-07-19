<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetches_stores_and_dedupes_items(): void
    {
        Http::fake(['rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://rss.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->assertEquals(2, ContentItem::count());

        // Second run: same items, nothing duplicated
        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->assertEquals(2, ContentItem::count());

        $source->refresh();
        $this->assertNotNull($source->last_succeeded_at);
        $this->assertEquals(0, $source->consecutive_failures);
    }

    public function test_ics_events_upsert_approved(): void
    {
        Http::fake(['cal.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
        Source::factory()->create(['type' => 'ics', 'url' => 'https://cal.example/cal.ics']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $this->artisan('app:fetch-sources')->assertSuccessful();

        $this->assertEquals(3, Event::count());
        $this->assertEquals(3, Event::where('status', 'approved')->count());
    }

    public function test_ics_utc_times_persist_as_local_wall_clock(): void
    {
        Http::fake(['cal.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
        Source::factory()->create(['type' => 'ics', 'url' => 'https://cal.example/cal.ics']);

        $this->artisan('app:fetch-sources')->assertSuccessful();

        // Fixture DTSTART:20260801T020000Z == 2026-07-31 19:00 America/Los_Angeles (PDT)
        $event = Event::where('external_uid', 'evt-100@example.org')->first();
        $this->assertSame('2026-07-31 19:00', $event->starts_at->format('Y-m-d H:i'));
    }

    public function test_failure_isolated_and_counted(): void
    {
        Http::fake([
            'down.example/*' => Http::response('', 500),
            'rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml'))),
        ]);
        $bad = Source::factory()->create(['type' => 'rss', 'url' => 'https://down.example/feed.xml']);
        Source::factory()->create(['type' => 'rss', 'url' => 'https://rss.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();

        $this->assertEquals(1, $bad->fresh()->consecutive_failures);
        $this->assertEquals(2, ContentItem::count()); // good source still processed
    }

    public function test_success_resets_failure_streak(): void
    {
        Http::fake(['rss.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
        $source = Source::factory()->create([
            'type' => 'rss', 'url' => 'https://rss.example/feed.xml',
            'consecutive_failures' => 3, 'failure_notified_at' => now(),
        ]);

        $this->artisan('app:fetch-sources')->assertSuccessful();

        $source->refresh();
        $this->assertEquals(0, $source->consecutive_failures);
        $this->assertNull($source->failure_notified_at);
    }

    public function test_inactive_sources_skipped(): void
    {
        Http::fake();
        Source::factory()->create(['active' => false, 'url' => 'https://never.example/feed.xml']);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_scraped_events_created_pending_once_and_not_duplicated(): void
    {
        Http::fake(['data.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/orgevents.json')))]);
        Source::factory()->create([
            'type' => 'dataset',
            'url' => 'https://data.example/events.json',
            'selector_config' => [
                'items_path' => 'events',
                'title_field' => 'name',
                'url_field' => 'link',
                'summary_field' => 'where',
                'starts_at_field' => 'when',
                'kind' => 'event',
            ],
        ]);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $firstContentItemCount = ContentItem::count();
        $this->assertSame(2, Event::count(), 'both dated dataset events create pending Events on first run');
        $this->assertSame(2, Event::where('status', 'pending')->whereNotNull('source_id')->count());

        // Second run: same items re-fetched; content_item dedupe (source_id + content_hash)
        // prevents the pending-event branch from firing again, so counts stay stable.
        $this->artisan('app:fetch-sources')->assertSuccessful();
        $secondContentItemCount = ContentItem::count();

        $this->assertSame(2, Event::count(), 'pending events are not duplicated on repeat fetch');
        $this->assertSame(2, Event::where('status', 'pending')->count());
        $this->assertSame($firstContentItemCount, $secondContentItemCount, 'content-item dedupe stable across runs');

        // Note: dateless scraped items (e.g. HtmlFetcher without starts_at_selector
        // configured) remain content-items only and never reach the Event table.
    }
}
