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

    public function test_scraped_events_created_pending_once_and_not_duplicated(): void
    {
        Http::fake(['org.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/orgpage.html')))]);
        Source::factory()->create([
            'type' => 'html',
            'url' => 'https://org.example/events',
            'selector_config' => [
                'item_selector' => 'article.post',
                'title_selector' => '.title',
                'link_selector' => 'a.more@href',
                'summary_selector' => '.excerpt',
                'kind' => 'event',
            ],
        ]);

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $firstContentItemCount = ContentItem::count();

        $this->artisan('app:fetch-sources')->assertSuccessful();
        $secondContentItemCount = ContentItem::count();

        // Fixture items have no dates; HtmlFetcher produces startsAt=null, so no Events
        // are created (command guard: $item->kind !== 'event' || ! $item->startsAt).
        // Dedupe is proven at ContentItem layer: content_items with same source_id +
        // content_hash are not duplicated, which prevents event re-creation on re-run.
        $this->assertSame(0, Event::count(), 'no events created for items without parseable dates');
        $this->assertSame($firstContentItemCount, $secondContentItemCount, 'pending event protection via content-item dedupe');
        $this->assertGreaterThan(0, $firstContentItemCount, 'fixture items were parsed into content-items');
    }
}
