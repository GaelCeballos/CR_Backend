<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * problemaPassword() del frontend: minimo 8 caracteres, letras y numeros.
 */
class PasswordSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) < 8) {
            $fail('La contraseña debe tener al menos 8 caracteres.');

            return;
        }

        if (! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value)) {
            $fail('La contraseña debe incluir letras y números.');
        }
    }
}
