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

        $this->assertEquals(2, Event::count());
        $this->assertEquals(2, Event::where('status', 'approved')->count());
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
}
