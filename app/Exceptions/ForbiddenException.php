<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message, string $code = 'FORBIDDEN')
    {
        parent::__construct(403, $code, $message);
    }
}
