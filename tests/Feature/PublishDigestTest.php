<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishDigestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active', 'role' => 'admin']);
    }

    public function test_publish_action_publishes_and_audit_logs(): void
    {
        $post = Post::factory()->create(['status' => 'review', 'title' => 'Weekly Digest 1']);

        $this->actingAs($this->admin())->post("/admin/posts/{$post->id}/publish")->assertRedirect();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'post_publish']);
    }

    public function test_republish_preserves_original_published_at(): void
    {
        $original = now()->subDays(3)->startOfSecond();
        $post = Post::factory()->create(['status' => 'review', 'published_at' => $original]);

        $this->actingAs($this->admin())->post("/admin/posts/{$post->id}/publish");

        $this->assertTrue($post->fresh()->published_at->equalTo($original));
    }

    public function test_email_view_renders_markdown_and_raw_source(): void
    {
        $post = Post::factory()->create([
            'status' => 'review',
            'body' => "## News\n\n- [Big Story](https://example.org/story) happened.",
        ]);

        $response = $this->actingAs($this->admin())->get("/admin/posts/{$post->id}/email");

        $response->assertOk();
        $response->assertSee('<h2', false);                       // markdown rendered to HTML
        $response->assertSee('https://example.org/story');
        $response->assertSee('## News');                          // raw markdown in the textarea
    }

    public function test_email_view_escapes_raw_html_in_body(): void
    {
        $post = Post::factory()->create(['body' => 'Hello <script>alert(1)</script> world']);

        $this->actingAs($this->admin())->get("/admin/posts/{$post->id}/email")
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_public_news_page_renders_markdown(): void
    {
        $post = Post::factory()->published()->create(['body' => "## Around Town\n\nA **bold** week."]);

        $response = $this->get('/news/' . $post->slug);

        $response->assertOk();
        $response->assertSee('<h2', false);
        $response->assertSee('<strong>bold</strong>', false);
    }

    public function test_email_view_blocked_for_non_admin(): void
    {
        $member = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $post = Post::factory()->create();

        $this->actingAs($member)->get("/admin/posts/{$post->id}/email")->assertForbidden();
    }

    public function test_review_queue_links_pending_digest_draft(): void
    {
        Post::factory()->create(['status' => 'review', 'title' => 'Lake City This Week — Jul 25, 2026']);

        $this->actingAs($this->admin())->get('/admin/review')
            ->assertOk()->assertSee('Lake City This Week');
    }
}
