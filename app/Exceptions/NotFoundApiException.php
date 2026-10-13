<?php

namespace App\Exceptions;

/**
 * Error 404: el recurso solicitado (folio, usuario, etc.) no existe.
 */
class NotFoundApiException extends ApiException
{
    /**
     * @param  string  $message  mensaje especifico de que no se encontro
     */
    public function __construct(string $message = 'No encontrado.')
    {
        parent::__construct(404, 'NOT_FOUND', $message);
    }
}
