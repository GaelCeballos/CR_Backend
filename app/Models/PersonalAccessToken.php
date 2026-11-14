<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Token de Sanctum que rechaza en silencio un bearer con id no numerico.
 * Sanctum busca "abc|x" con find('abc') y Postgres truena (columna bigint):
 * cualquiera sin cuenta podia provocar un 500 y llenar el log con trazas
 * (auditoria run-1, H13). Un id invalido es simplemente un token inexistente.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @param  string  $token  token completo tal como llego en la cabecera
     * @return static|null el token si existe y coincide, o null
     */
    public static function findToken($token)
    {
        if (str_contains($token, '|') && ! ctype_digit(strstr($token, '|', true))) {
            return null;
        }

        return parent::findToken($token);
    }
}
