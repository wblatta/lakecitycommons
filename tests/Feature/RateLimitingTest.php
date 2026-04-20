<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_rate_limited_after_3_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'test@example.com']);
        }

        $response = $this->post('/forgot-password', ['email' => 'test@example.com']);
        $response->assertStatus(429);
    }

    public function test_message_poll_rate_limited_after_30_attempts(): void
    {
        $user = User::factory()->create(['status' => 'active', 'role' => 'member']);
        $this->actingAs($user);

        $thread = Thread::create(['request_id' => null, 'subject' => 'Test']);
        $thread->participants()->create(['user_id' => $user->id]);

        for ($i = 0; $i < 30; $i++) {
            $this->get("/messages/{$thread->id}/poll?after=0");
        }

        $response = $this->get("/messages/{$thread->id}/poll?after=0");
        $response->assertStatus(429);
    }
}
