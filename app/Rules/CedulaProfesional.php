<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que una cedula profesional tenga al menos 5 caracteres, solo letras y numeros.
 */
class CedulaProfesional implements ValidationRule
{
    /**
     * Revisa el valor recibido y llama a $fail() si no cumple el formato esperado.
     *
     * @param  string  $attribute  nombre del campo que se esta validando
     * @param  mixed  $value  valor enviado por el cliente
     * @param  Closure  $fail  callback para reportar el error de validacion
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z0-9]{5,}$/', $value)) {
            $fail('La cédula debe tener al menos 5 caracteres, solo letras y números.');
        }
    }
}
