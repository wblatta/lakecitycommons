<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureReferralToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'referral' => EnsureReferralToken::class,
            'admin' => AdminOnly::class,
            'active' => EnsureActiveUser::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        $middleware->appendToGroup('web', EnsureActiveUser::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
