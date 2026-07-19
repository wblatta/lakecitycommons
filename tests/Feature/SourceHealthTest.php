<?php

namespace Tests\Feature;

use App\Mail\SourceFailingMail;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SourceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_consecutive_failure_emails_admins_once(): void
    {
        Mail::fake();
        Http::fake(['down.example/*' => Http::response('', 500)]);
        User::factory()->create(['status' => 'active', 'role' => 'admin', 'email' => 'admin@example.org']);
        $source = Source::factory()->create([
            'type' => 'rss', 'url' => 'https://down.example/feed.xml', 'consecutive_failures' => 1,
        ]);

        $this->artisan('app:fetch-sources'); // failure #2 -> notify
        Mail::assertSent(SourceFailingMail::class, 1);
        $this->assertNotNull($source->fresh()->failure_notified_at);

        $this->artisan('app:fetch-sources'); // failure #3 -> already notified, no new mail
        Mail::assertSent(SourceFailingMail::class, 1);
    }

    public function test_first_failure_does_not_email(): void
    {
        Mail::fake();
        Http::fake(['down.example/*' => Http::response('', 500)]);
        User::factory()->create(['status' => 'active', 'role' => 'admin']);
        Source::factory()->create(['type' => 'rss', 'url' => 'https://down.example/feed.xml']);

        $this->artisan('app:fetch-sources');
        Mail::assertNothingSent();
    }

    public function test_review_queue_shows_failing_source_banner(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'role' => 'admin']);
        Source::factory()->create(['name' => 'Dead Feed', 'consecutive_failures' => 2]);

        $this->actingAs($admin)->get('/admin/review')
            ->assertOk()->assertSee('Dead Feed');
    }
}
