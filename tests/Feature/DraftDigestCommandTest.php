<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Services\Digest\DigestDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DraftDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_creates_review_draft_and_marks_items(): void
    {
        $this->admin();
        ContentItem::factory()->count(3)->create();
        Event::factory()->create(['starts_at' => now()->addDays(3)]);

        $this->app->bind(DigestDrafter::class, fn () => new class implements DigestDrafter {
            public function draft(Collection $items, Collection $events): string
            {
                return "## News\n\nDrafted digest with {$items->count()} items.";
            }
        });

        $this->artisan('app:draft-digest')->assertSuccessful();

        $post = Post::first();
        $this->assertSame('review', $post->status);
        $this->assertStringContainsString('Drafted digest with 3 items', $post->body);
        $this->assertEquals(0, ContentItem::unprocessed()->count());
        $this->assertEquals(3, ContentItem::where('status', 'in_digest')->count());
    }

    public function test_drafter_failure_falls_back_to_raw_list(): void
    {
        $this->admin();
        ContentItem::factory()->create(['title' => 'Rescue Me Item']);

        $this->app->bind(DigestDrafter::class, fn () => new class implements DigestDrafter {
            public function draft(Collection $items, Collection $events): string
            {
                throw new \RuntimeException('API down');
            }
        });

        $this->artisan('app:draft-digest')->assertSuccessful();

        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame('review', $post->status);
        $this->assertStringContainsString('Rescue Me Item', $post->body);
    }

    public function test_skips_when_recent_digest_draft_exists(): void
    {
        $this->admin();
        ContentItem::factory()->create();
        Post::factory()->create(['status' => 'review', 'created_at' => now()->subDay()]);

        $this->artisan('app:draft-digest')->assertSuccessful();
        $this->assertEquals(1, Post::count());
    }

    public function test_skips_when_nothing_to_report(): void
    {
        $this->admin();
        $this->artisan('app:draft-digest')->assertSuccessful();
        $this->assertEquals(0, Post::count());
    }

    public function test_raw_list_drafter_groups_by_kind(): void
    {
        $items = collect([
            ContentItem::factory()->create(['kind' => 'news', 'title' => 'A News Story']),
            ContentItem::factory()->create(['kind' => 'notice', 'title' => 'A Permit Notice']),
        ]);
        $events = collect([Event::factory()->create(['title' => 'Saturday Market', 'starts_at' => now()->addDays(2)])]);

        $body = (new \App\Services\Digest\RawListDrafter)->draft($items, $events);

        $this->assertStringContainsString('A News Story', $body);
        $this->assertStringContainsString('A Permit Notice', $body);
        $this->assertStringContainsString('Saturday Market', $body);
    }
}
