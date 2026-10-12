<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use App\Rules\CedulaProfesional;
use App\Rules\NombrePersona;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos del formulario de "editar mi perfil": nombre, correo,
 * cedula y universidad. El rol no viene en este formulario, nunca se toca aqui.
 */
class UpdateMeRequest extends FormRequest
{
    /**
     * @return bool siempre true: cualquier usuario autenticado puede editar su propio perfil
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Cedula y universidad son obligatorias solo si la cuenta es de servicios medicos.
     *
     * @return array<string, array<mixed>> reglas de validacion por campo
     */
    public function rules(): array
    {
        $esMedico = $this->user()->rol === RolUsuario::ServiciosMedicos;

        return [
            'nombre_completo' => ['required', 'string', new NombrePersona()],
            'email' => ['required', 'email'],
            'cedula_profesional' => [$esMedico ? 'required' : 'nullable', 'string', new CedulaProfesional()],
            'universidad' => [$esMedico ? 'required' : 'nullable', 'string', 'min:2'],
        ];
    }

    /**
     * @return array<string, string> mensajes personalizados por regla fallida
     */
    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'Escribe el nombre completo.',
            'email.required' => 'El correo no es válido.',
            'email.email' => 'El correo no es válido.',
            'cedula_profesional.required' => 'La cédula debe tener al menos 5 caracteres, solo letras y números.',
            'universidad.required' => 'Escribe la universidad (al menos 2 caracteres).',
            'universidad.min' => 'Escribe la universidad (al menos 2 caracteres).',
        ];
    }
}
