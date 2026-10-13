<?php

namespace App\Exceptions;

/**
 * Error 403: el usuario esta autenticado pero no puede hacer esta accion
 * (rol insuficiente, cuenta inactiva, perfil distinto al rol real, etc.).
 */
class ForbiddenException extends ApiException
{
    /**
     * @param  string  $message  mensaje especifico de por que se nego el acceso
     * @param  string  $code  identificador del error (por defecto "FORBIDDEN")
     */
    public function __construct(string $message, string $code = 'FORBIDDEN')
    {
        parent::__construct(403, $code, $message);
    }
}
