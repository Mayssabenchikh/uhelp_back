<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global
            $middleware->prepend(HandleCors::class);

        // Groupe API
        $middleware->group('api', [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Alias
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);

    })
    
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
