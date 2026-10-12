<?php

namespace App\Exceptions;

class NotFoundApiException extends ApiException
{
    public function __construct(string $message = 'No encontrado.')
    {
        parent::__construct(404, 'NOT_FOUND', $message);
    }
}
