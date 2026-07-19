<?php

namespace Tests\Feature\Auth;

use App\Models\ReferralToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_registration_screen_renders_with_valid_referral_token(): void
    {
        $inviter = User::factory()->create(['status' => 'active']);
        $token = ReferralToken::create([
            'token' => str_repeat('a', 64),
            'inviter_id' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->get('/register/' . $token->token)->assertOk();
    }
}
