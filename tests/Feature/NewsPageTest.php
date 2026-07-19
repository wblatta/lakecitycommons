<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_posts_only(): void
    {
        Post::factory()->published()->create(['title' => 'Weekly Digest No. 1']);
        Post::factory()->create(['title' => 'Secret Draft', 'status' => 'draft']);

        $response = $this->get('/news');

        $response->assertOk();
        $response->assertSee('Weekly Digest No. 1');
        $response->assertDontSee('Secret Draft');
    }

    public function test_show_renders_published_post_by_slug(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Big Park News']);

        $this->assertEquals('big-park-news', $post->slug);

        $response = $this->get('/news/' . $post->slug);
        $response->assertOk();
        $response->assertSee('Big Park News');
    }

    public function test_draft_post_404s_publicly(): void
    {
        $post = Post::factory()->create(['status' => 'draft']);

        $this->get('/news/' . $post->slug)->assertNotFound();
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $a = Post::factory()->published()->create(['title' => 'Cleanup Day']);
        $b = Post::factory()->published()->create(['title' => 'Cleanup Day']);

        $this->assertEquals('cleanup-day', $a->slug);
        $this->assertEquals('cleanup-day-2', $b->slug);
    }

    public function test_admin_can_set_status(): void
    {
        $admin = \App\Models\User::factory()->create(['status' => 'active', 'role' => 'admin']);

        $this->actingAs($admin)->post('/admin/posts', [
            'title' => 'Hello Lake City',
            'body' => 'First post.',
            'status' => 'published',
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::where('title', 'Hello Lake City')->first();
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }
}
