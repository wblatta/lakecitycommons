<?php

namespace Tests\Feature\Fetchers;

use App\Models\Source;
use App\Services\Fetchers\HtmlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HtmlFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrapes_items_with_selectors_and_resolves_relative_urls(): void
    {
        Http::fake(['example.org/*' => Http::response(file_get_contents(base_path('tests/fixtures/orgpage.html')))]);
        $source = Source::factory()->create([
            'type' => 'html',
            'url' => 'https://example.org/news',
            'selector_config' => [
                'item_selector' => 'article.post',
                'title_selector' => '.title',
                'link_selector' => 'a.more@href',
                'summary_selector' => '.excerpt',
            ],
        ]);

        $items = (new HtmlFetcher)->fetch($source);

        $this->assertCount(2, $items);
        $this->assertSame('Fall Festival Announced', $items[0]->title);
        $this->assertSame('https://example.org/news/fall-festival', $items[0]->url);
        $this->assertSame('https://example.org/news/minutes', $items[1]->url);
    }

    public function test_missing_required_config_throws(): void
    {
        $source = Source::factory()->create(['type' => 'html', 'selector_config' => null]);
        $this->expectException(\RuntimeException::class);
        (new HtmlFetcher)->fetch($source);
    }
}
