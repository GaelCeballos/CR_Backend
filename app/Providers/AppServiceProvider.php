<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios de la aplicacion (ninguno por ahora).
     */
    public function register(): void
    {
        //
    }

    /**
     * Define el limitador de intentos de login: 5 por minuto, contados por
     * IP y por cuenta a la vez, para frenar tanto un ataque desde una sola
     * IP como uno distribuido contra una sola cuenta.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $cuenta = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($request->ip().'|'.$cuenta);
        });
    }
}
