<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Agrega CORS al grupo API
        $middleware->appendToGroup('api', [
            HandleCors::class,
        ]);

        // (Opcional) Alias, por si quieres referenciarlo como 'cors'
        // $middleware->alias([
        //     'cors' => HandleCors::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Personaliza el manejo de excepciones aquí si lo necesitas
    })
    ->create();
