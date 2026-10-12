<?php

namespace App\Http\Requests;

use App\Rules\PasswordSegura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Valida el cambio de contraseña propio: la actual debe ser correcta, y la
 * nueva debe cumplir el formato minimo y ser distinta de la actual.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * @return bool siempre true: cualquier usuario autenticado puede cambiar su propia contraseña
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>> reglas de validacion por campo
     */
    public function rules(): array
    {
        $usuario = $this->user();

        return [
            'actual' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($usuario) {
                    if (! Hash::check($value, $usuario->hashed_password)) {
                        $fail('La contraseña actual no es correcta.');
                    }
                },
            ],
            'nueva' => [
                'required', 'string', new PasswordSegura(),
                function (string $attribute, mixed $value, \Closure $fail) use ($usuario) {
                    if (Hash::check($value, $usuario->hashed_password)) {
                        $fail('La nueva contraseña debe ser distinta de la actual.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string> mensajes personalizados por regla fallida
     */
    public function messages(): array
    {
        return [
            'actual.required' => 'La contraseña actual no es correcta.',
            'nueva.required' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
