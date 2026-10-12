<?php

namespace App\Exceptions;

class InvalidCredentialsException extends ApiException
{
    public function __construct(string $message = 'Correo o contraseña incorrectos.')
    {
        parent::__construct(401, 'INVALID_CREDENTIALS', $message);
    }
}
