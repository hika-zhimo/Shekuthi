<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Token (Sanctum) auth for the app; session + CSRF for the website.
        // Rate limits are applied per-route (see routes/api.php).
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active' => EnsureActiveAccount::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);

        // Security headers on every response (M7.4).
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
