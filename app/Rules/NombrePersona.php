<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NOMBRE_RE del frontend: letras con acentos, ñ, ü, espacios y puntos. Sin guiones ni apostrofes.
 */
class NombrePersona implements ValidationRule
{
    public function __construct(private string $mensaje = 'El nombre solo debe contener letras, espacios y puntos (ej. J. Jesus)')
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.]+$/u', $value)) {
            $fail($this->mensaje);
        }
    }
}
