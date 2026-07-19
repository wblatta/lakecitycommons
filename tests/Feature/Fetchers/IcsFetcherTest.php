<?php

namespace Tests\Feature\Fetchers;

use App\Models\Source;
use App\Services\Fetchers\IcsFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IcsFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_vevents_with_folded_lines_and_all_day(): void
    {
        Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
        $source = Source::factory()->create(['type' => 'ics', 'url' => 'https://example.org/cal.ics']);

        $items = (new IcsFetcher)->fetch($source);

        $this->assertCount(3, $items);
        $this->assertSame('event', $items[0]->kind);
        $this->assertSame('Summer Concert in the Park', $items[0]->title);
        $this->assertSame('evt-100@example.org', $items[0]->externalUid);
        $this->assertSame('2026-08-01 02:00:00', $items[0]->startsAt->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Lake City Community Center', $items[0]->location);
        $this->assertSame('All-Day Cleanup', $items[1]->title);
        $this->assertNotNull($items[1]->startsAt); // date-only DTSTART parsed as start of day
        $this->assertSame('Explicit DateTime Form', $items[2]->title);
        $this->assertSame('2026-08-20 17:00', $items[2]->startsAt->utc()->format('Y-m-d H:i')); // VALUE=DATE-TIME keeps its time
    }

    public function test_unescapes_ics_escape_sequences_in_description(): void
    {
        Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/calendar.ics')))]);
        $source = Source::factory()->create(['type' => 'ics', 'url' => 'https://example.org/cal.ics']);

        $items = (new IcsFetcher)->fetch($source);

        // unescape() restores , ; and newline; the fetcher then squishes whitespace
        $this->assertSame('Live music, food trucks; bring chairs. All ages', $items[0]->summary);
    }
}
