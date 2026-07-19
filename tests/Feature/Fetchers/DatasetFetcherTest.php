<?php

namespace Tests\Feature\Fetchers;

use App\Models\Source;
use App\Services\Fetchers\DatasetFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatasetFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_json_records(): void
    {
        Http::fake(['data.example/*' => Http::response(file_get_contents(base_path('tests/fixtures/permits.json')))]);
        $source = Source::factory()->create([
            'type' => 'dataset',
            'url' => 'https://data.example/permits.json',
            'selector_config' => [
                'items_path' => 'results',
                'title_field' => 'description',
                'url_field' => 'permit_url',
                'date_field' => 'issued',
                'summary_field' => 'address',
            ],
        ]);

        $items = (new DatasetFetcher)->fetch($source);

        $this->assertCount(2, $items);
        $this->assertSame('notice', $items[0]->kind);
        $this->assertSame('New mixed-use building', $items[0]->title);
        $this->assertSame('2026-07-15', $items[0]->publishedAt->format('Y-m-d'));
    }
}
