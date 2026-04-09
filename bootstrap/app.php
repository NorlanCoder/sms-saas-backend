<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/fedapay',
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ApiLogger::class,
        ]);

        $middleware->alias([
            'is.admin' => \App\Http\Middleware\IsAdmin::class,
            'is.super_admin' => \App\Http\Middleware\IsSuperAdmin::class,
            'verify.api.signature' => \App\Http\Middleware\VerifyApiSignature::class,
            'api.logger' => \App\Http\Middleware\ApiLogger::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
