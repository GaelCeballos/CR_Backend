<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepcion base para devolver siempre el mismo formato de error:
 * { "error": { "code", "message", "fields"? } }
 */
class ApiException extends RuntimeException
{
    /**
     * @param  int  $status  codigo HTTP de la respuesta (400, 401, 403, 404, 409...)
     * @param  string  $errorCode  identificador corto del error (p. ej. "VALIDATION_ERROR")
     * @param  string  $message  mensaje legible para mostrar al usuario
     * @param  array<string, string>|null  $fields  errores por campo, si aplica
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?array $fields = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Da el cuerpo JSON que se debe devolver al cliente para esta excepcion.
     *
     * @return array{error: array{code: string, message: string, fields?: array<string, string>}}
     */
    public function toResponseArray(): array
    {
        $error = ['code' => $this->errorCode, 'message' => $this->getMessage()];

        if ($this->fields !== null) {
            $error['fields'] = $this->fields;
        }

        return ['error' => $error];
    }
}
