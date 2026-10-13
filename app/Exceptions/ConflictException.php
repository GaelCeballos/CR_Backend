<?php

namespace App\Exceptions;

/**
 * Error 409: la accion choca con un dato ya existente (correo duplicado,
 * CURP/NSS duplicado, o alguna regla de "al menos un admin activo").
 */
class ConflictException extends ApiException
{
    /**
     * @param  string  $message  mensaje especifico del conflicto
     * @param  array<string, string>|null  $fields  campo(s) que causan el conflicto, si aplica
     */
    public function __construct(string $message, ?array $fields = null)
    {
        parent::__construct(409, 'CONFLICT', $message, $fields);
    }
}
