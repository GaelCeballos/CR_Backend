<?php

namespace App\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(string $message, ?array $fields = null)
    {
        parent::__construct(409, 'CONFLICT', $message, $fields);
    }
}
