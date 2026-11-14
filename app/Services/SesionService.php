<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reglas de la sesion (token Bearer de Sanctum):
 * - Una sola sesion por cuenta: un login nuevo deja inservibles los anteriores.
 * - Vence tras `sanctum.inactividad` minutos sin actividad; cada peticion la
 *   extiende, pero nunca mas alla de `sanctum.expiration` desde el login.
 * - Si se rechaza un token, se puede saber por que (reemplazado o vencido) para
 *   que el front lo explique en lugar de un generico "No has iniciado sesion".
 */
class SesionService
{
    /** Nombre con el que se marca un token que otro login dejo sin efecto. */
    public const TOKEN_REEMPLAZADO = 'reemplazada';

    /** Nombre de los tokens vigentes. */
    public const TOKEN_ACTIVO = 'api';

    /**
     * Cierra las sesiones anteriores de la cuenta y emite un token nuevo.
     *
     * Los tokens anteriores no se borran: se marcan como reemplazados y vencidos,
     * asi el equipo que se quedo con uno recibe SESSION_REPLACED en vez de un 401
     * sin explicacion. Se limpian despues con purgarVencidos().
     *
     * @param  User  $usuario  cuenta que acaba de iniciar sesion
     * @return string token en texto plano para el cliente
     */
    public function iniciar(User $usuario): string
    {
        $ahora = now();

        $usuario->tokens()
            ->where('name', self::TOKEN_ACTIVO)
            ->where('expires_at', '>', $ahora)
            ->update(['name' => self::TOKEN_REEMPLAZADO, 'expires_at' => $ahora->copy()->subSecond()]);

        $this->purgarVencidos();

        return $usuario->createToken(
            self::TOKEN_ACTIVO,
            ['*'],
            $ahora->copy()->addMinutes($this->minutosInactividad()),
        )->plainTextToken;
    }

    /**
     * Extiende la sesion por actividad: vence `inactividad` minutos despues de
     * ahora, sin pasar del tope absoluto contado desde el login.
     *
     * Solo escribe si gana al menos un minuto, para no hacer un UPDATE en cada
     * peticion de una pantalla que dispara varias a la vez.
     *
     * @param  PersonalAccessToken  $token  token con el que se hizo la peticion
     */
    public function extender(PersonalAccessToken $token): void
    {
        $nuevo = now()->addMinutes($this->minutosInactividad());
        $tope = $this->tope($token);

        if ($nuevo->greaterThan($tope)) {
            $nuevo = $tope;
        }

        if ($token->expires_at === null || $nuevo->diffInSeconds($token->expires_at, false) < -60) {
            $token->forceFill(['expires_at' => $nuevo])->save();
        }
    }

    /**
     * Momento en que vence la sesion (lo que ocurra primero: inactividad o tope).
     *
     * @param  PersonalAccessToken  $token  token de la peticion
     * @return Carbon fecha y hora de vencimiento
     */
    public function venceEn(PersonalAccessToken $token): Carbon
    {
        $tope = $this->tope($token);

        return $token->expires_at && $token->expires_at->lessThan($tope) ? $token->expires_at : $tope;
    }

    /**
     * Explica por que se rechazo el token de una peticion.
     *
     * @param  string|null  $bearer  token en texto plano recibido en Authorization
     * @return string SESSION_REPLACED, SESSION_EXPIRED o UNAUTHENTICATED
     */
    public function motivoRechazo(?string $bearer): string
    {
        if (! $bearer) {
            return 'UNAUTHENTICATED';
        }

        // findToken compara el hash con hash_equals; no revela nada de un token ajeno.
        $token = PersonalAccessToken::findToken($bearer);

        if (! $token) {
            return 'UNAUTHENTICATED';
        }

        return $token->name === self::TOKEN_REEMPLAZADO ? 'SESSION_REPLACED' : 'SESSION_EXPIRED';
    }

    /**
     * Borra los tokens vencidos hace mas de un dia. Se llama en cada login
     * porque el plan gratuito de Render no ejecuta tareas programadas; el dia de
     * gracia permite seguir explicando el motivo al equipo que se quedo fuera.
     */
    public function purgarVencidos(): void
    {
        PersonalAccessToken::where('expires_at', '<', now()->subDay())->delete();
    }

    /**
     * Devuelve la llave del borrador de la cuenta y la crea si aun no tiene.
     * Es estable (no cambia en cada login) para que un borrador guardado antes
     * de que se cerrara la sesion se pueda descifrar al volver a entrar.
     *
     * @param  User  $usuario  cuenta autenticada
     * @return string 32 bytes aleatorios en base64 (llave AES-256)
     */
    public function llaveBorrador(User $usuario): string
    {
        if (! $usuario->llave_borrador) {
            $usuario->forceFill(['llave_borrador' => base64_encode(random_bytes(32))])->save();
        }

        return $usuario->llave_borrador;
    }

    /**
     * Tope absoluto de la sesion: login + `sanctum.expiration` minutos.
     */
    private function tope(PersonalAccessToken $token): Carbon
    {
        return $token->created_at->copy()->addMinutes((int) config('sanctum.expiration'));
    }

    private function minutosInactividad(): int
    {
        return (int) config('sanctum.inactividad');
    }
}
