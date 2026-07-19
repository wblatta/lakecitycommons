<?php

namespace Tests\Unit;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_casts_and_scope(): void
    {
        $source = Source::factory()->create(['selector_config' => ['item_selector' => '.post'], 'active' => true]);
        Source::factory()->create(['active' => false]);

        $this->assertSame('.post', $source->fresh()->selector_config['item_selector']);
        $this->assertEquals(1, Source::active()->count());
    }

    public function test_content_item_dedupe_unique_constraint(): void
    {
        $source = Source::factory()->create();
        ContentItem::factory()->create(['source_id' => $source->id, 'content_hash' => str_repeat('a', 64)]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ContentItem::factory()->create(['source_id' => $source->id, 'content_hash' => str_repeat('a', 64)]);
    }

    public function test_event_upsert_by_source_and_external_uid(): void
    {
        $source = Source::factory()->create(['type' => 'ics']);

        Event::updateOrCreate(
            ['source_id' => $source->id, 'external_uid' => 'uid-1@cal'],
            ['title' => 'Original', 'starts_at' => now()->addDay(), 'status' => 'approved']
        );
        Event::updateOrCreate(
            ['source_id' => $source->id, 'external_uid' => 'uid-1@cal'],
            ['title' => 'Updated', 'starts_at' => now()->addDays(2), 'status' => 'approved']
        );

        $this->assertEquals(1, Event::count());
        $this->assertSame('Updated', Event::first()->title);
    }
}
