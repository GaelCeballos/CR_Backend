<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CEDULA_RE del frontend: al menos 5 caracteres, solo letras y numeros.
 */
class CedulaProfesional implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z0-9]{5,}$/', $value)) {
            $fail('La cédula debe tener al menos 5 caracteres, solo letras y números.');
        }
    }
}
