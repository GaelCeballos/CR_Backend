<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
     * Define los limitadores de credenciales (auditoria run-1, H02/H04):
     *  - login: 5 por minuto por cuenta, sin importar la IP (un ataque repartido
     *    en muchas IP contra una cuenta ya no se multiplica), y 20 por minuto por
     *    IP sin importar la cuenta (frena probar una contraseña contra muchas
     *    cuentas). 20 deja margen a los equipos de la delegacion, que salen a
     *    internet con la misma IP y entran casi todos al inicio del turno.
     *  - cambio-password: 5 por minuto por usuario, para que un token robado no
     *    sirva para adivinar la contraseña actual.
     */
    public function boot(): void
    {
        // Token con validacion del id antes de consultar (ver App\Models\PersonalAccessToken).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('login', function (Request $request) {
            // Un email que no es texto (p. ej. un arreglo) tronaba aqui con un 500
            // antes de llegar a la validacion: se cuenta como cuenta vacia.
            $email = $request->input('email');
            $cuenta = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('cuenta:'.$cuenta),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('cambio-password', function (Request $request) {
            return Limit::perMinute(5)->by('usuario:'.$request->user()?->id);
        });
    }
}
