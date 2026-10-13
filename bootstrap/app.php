<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\BlockIfTemporaryPassword;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Esta API no tiene ruta "login": nunca debe intentar redirigir ahi
        // cuando falta autenticacion, solo debe responder el error JSON.
        Authenticate::redirectUsing(fn () => null);

        // Render (y cualquier PaaS) entrega las peticiones a traves de su propio
        // proxy: sin esto, $request->ip() siempre da la IP interna del proxy (no
        // la del cliente real), rompiendo el limite de intentos de login por IP.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureActiveUser::class,
            'no-temp-password' => BlockIfTemporaryPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        ApiExceptionHandler::register($exceptions);
    })->create();
