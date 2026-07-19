<?php
// tests/Feature/FeedTest.php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_feed_lists_published_posts(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Digest & Special <Edition>']);
        Post::factory()->create(['title' => 'Hidden Draft', 'status' => 'draft']);

        $response = $this->get('/feed');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('<rss version="2.0"', $content);
        $this->assertStringContainsString('Digest &amp; Special &lt;Edition&gt;', $content);
        $this->assertStringContainsString(route('news.show', $post), $content);
        $this->assertStringNotContainsString('Hidden Draft', $content);
    }

    public function test_sitemap_contains_static_pages_and_posts(): void
    {
        $post = Post::factory()->published()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('<urlset', $content);
        foreach (['/', '/news', '/events', '/directory', '/submit'] as $path) {
            $this->assertStringContainsString('<loc>' . url($path) . '</loc>', $content);
        }
        $this->assertStringContainsString('<loc>' . route('news.show', $post) . '</loc>', $content);
    }
}
