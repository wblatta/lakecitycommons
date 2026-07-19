<?php

namespace App\Providers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user->status === 'pending') {
                $event->user->update(['status' => 'active']);
            }
        });

        RateLimiter::for('message-poll', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('submissions', function (Request $request) {
            return Limit::perDay(5)->by($request->ip());
        });
    }
}
