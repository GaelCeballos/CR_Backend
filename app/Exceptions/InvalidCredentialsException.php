<?php

namespace App\Exceptions;

/**
 * Error 401 por correo o contraseña incorrectos en el login.
 */
class InvalidCredentialsException extends ApiException
{
    /**
     * @param  string  $message  mensaje a mostrar (usa el texto generico por defecto)
     */
    public function __construct(string $message = 'Correo o contraseña incorrectos.')
    {
        parent::__construct(401, 'INVALID_CREDENTIALS', $message);
    }
}
