<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Configuration CSRF directe
        $middleware->validateCsrfTokens(except: [
            //'api/*',
            'api/webhooks/fedapay',
            // Ajoutez d'autres routes à exclure ici
        ]);

        $middleware->alias([
            'admin.auth' => App\Http\Middleware\AdminAuthMiddleware::class,
            'device.auth' => App\Http\Middleware\DeviceAuthMiddleware::class
        ]);

        // // Remplacer le middleware CSRF par défaut
        // $middleware->web(replace: [
        //     \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        // ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('media-library:delete-old-temporary-uploads')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
