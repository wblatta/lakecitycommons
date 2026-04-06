<?php

namespace App\Providers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Activate user account on email verification
        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user->status === 'pending') {
                $event->user->update(['status' => 'active']);
            }
        });
    }
}
