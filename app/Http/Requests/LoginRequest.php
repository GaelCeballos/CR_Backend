<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string',
            'password' => 'required|string',
            'perfil' => 'required|in:admin,servicios_medicos',
        ];
    }

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
