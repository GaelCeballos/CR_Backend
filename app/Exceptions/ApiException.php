<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepcion base para el formato de error unico de la API (spec §4.0):
 * { "error": { "code", "message", "fields"? } }
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, string>|null  $fields
     */
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        string $message,
        public readonly ?array $fields = null,
    ) {
        parent::__construct($message);
    }

    public function toResponseArray(): array
    {
        $error = ['code' => $this->code, 'message' => $this->getMessage()];

        if ($this->fields !== null) {
            $error['fields'] = $this->fields;
        }

        return ['error' => $error];
    }
}
