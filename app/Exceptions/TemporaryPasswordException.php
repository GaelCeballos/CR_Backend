<?php

namespace App\Exceptions;

/**
 * Error 403 especifico: la cuenta tiene una contraseña temporal pendiente de
 * cambiar y esta intentando usar un endpoint distinto a /me o /me/password.
 */
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
