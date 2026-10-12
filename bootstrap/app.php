<?php

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
        // Twilio posts delivery-status callbacks without a CSRF token, so the
        // webhook must be exempt. It is safe: the callback only updates the
        // status of a message we ourselves created and identified by SID.
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/*',
            'webhooks/flutterwave',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();