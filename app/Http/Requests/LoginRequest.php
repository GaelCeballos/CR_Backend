<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos minimos del formulario de login: correo, contraseña y el
 * perfil ("Ingresar como") que el usuario eligio.
 */
class LoginRequest extends FormRequest
{
    /**
     * @return bool siempre true: el login es publico, no requiere autorizacion previa
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string> reglas de validacion por campo
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string',
            'password' => 'required|string',
            'perfil' => 'required|in:admin,servicios_medicos',
        ];
    }

    /**
     * @return array<string, string> mensajes personalizados por regla fallida
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo y contraseña.',
            'password.required' => 'Ingresa tu correo y contraseña.',
            'perfil.required' => 'Selecciona el perfil con el que quieres ingresar.',
            'perfil.in' => 'Selecciona el perfil con el que quieres ingresar.',
        ];
    }
}
