<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un nombre solo tenga letras (con acentos, ñ, ü), espacios y puntos.
 * No acepta guiones ni apostrofes.
 */
class NombrePersona implements ValidationRule
{
    /**
     * @param  string  $mensaje  texto de error a usar si el valor no cumple el formato
     */
    public function __construct(private string $mensaje = 'El nombre solo debe contener letras, espacios y puntos (ej. J. Jesus)')
    {
    }

    /**
     * Revisa el valor recibido y llama a $fail() si no cumple el formato esperado.
     *
     * @param  string  $attribute  nombre del campo que se esta validando
     * @param  mixed  $value  valor enviado por el cliente
     * @param  Closure  $fail  callback para reportar el error de validacion
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.]+$/u', $value)) {
            $fail($this->mensaje);
        }
    }
}
