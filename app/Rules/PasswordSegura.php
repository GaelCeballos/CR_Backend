<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que una contraseña nueva tenga al menos 8 caracteres e incluya letras y numeros.
 */
class PasswordSegura implements ValidationRule
{
    /**
     * Revisa el valor recibido y llama a $fail() con el mensaje correspondiente si no cumple.
     *
     * @param  string  $attribute  nombre del campo que se esta validando
     * @param  mixed  $value  valor enviado por el cliente
     * @param  Closure  $fail  callback para reportar el error de validacion
     */
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
