<?php

namespace App\Http\Requests;

use App\Rules\CedulaProfesional;
use App\Rules\NombrePersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la edicion de una cuenta existente por un administrador (nombre,
 * correo, rol, cedula, universidad y estado activo/inactivo).
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * @return bool la autorizacion real (solo admin) la hace el middleware de rol en la ruta
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
        $esMedico = $this->input('rol') === 'servicios_medicos';

        return [
            'nombre_completo' => ['required', 'string', new NombrePersona()],
            'email' => ['required', 'email'],
            'rol' => ['required', Rule::in(['admin', 'servicios_medicos'])],
            'cedula_profesional' => [$esMedico ? 'required' : 'nullable', 'string', new CedulaProfesional()],
            'universidad' => [$esMedico ? 'required' : 'nullable', 'string', 'min:2'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
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
