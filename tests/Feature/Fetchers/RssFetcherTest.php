<?php

namespace Tests\Feature\Fetchers;

use App\Models\Source;
use App\Services\Fetchers\RssFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_rss_items(): void
    {
        Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-rss.xml')))]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/feed.xml']);

        $items = (new RssFetcher)->fetch($source);

        $this->assertCount(2, $items);
        $this->assertSame('news', $items[0]->kind);
        $this->assertNotEmpty($items[0]->title);
        $this->assertNotNull($items[0]->url);
    }

    public function test_parses_atom_entries(): void
    {
        Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/feed-atom.xml')))]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/atom.xml']);

        $this->assertCount(2, (new RssFetcher)->fetch($source));
    }

    public function test_http_failure_throws(): void
    {
        Http::fake(['example.org/*' => Http::response('', 500)]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/feed.xml']);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        (new RssFetcher)->fetch($source);
    }

    public function test_unparseable_feed_throws(): void
    {
        Http::fake(['example.org/*' => Http::response('this is not xml at all <<<')]);
        $source = Source::factory()->create(['type' => 'rss', 'url' => 'https://example.org/feed.xml']);

        $this->expectException(\RuntimeException::class);
        (new RssFetcher)->fetch($source);
    }
}
