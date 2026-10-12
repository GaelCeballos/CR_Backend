<?php

namespace App\Exceptions;

class TemporaryPasswordException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            403,
            'PASSWORD_CHANGE_REQUIRED',
            'Debes cambiar tu contraseña temporal antes de continuar.',
        );
    }
}
