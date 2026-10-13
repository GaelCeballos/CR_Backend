<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fabrica usuarios de prueba con datos validos (nombre, correo, rol, contraseña).
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed> atributos por defecto para un usuario de prueba
     */
    public function definition(): array
    {
        return [
            'email' => Str::lower(Str::random(10)).'@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
            'nombre_completo' => 'USUARIO DE PRUEBA',
            'rol' => 'servicios_medicos',
            'estado' => 'activo',
            'cedula_profesional' => '12345678',
            'universidad' => 'UNIVERSIDAD DE COLIMA',
            'is_temporary_password' => false,
        ];
    }

    /**
     * Da el estado "admin": mismo usuario pero con rol de administrador.
     *
     * @return static fabrica configurada para crear un usuario administrador
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'rol' => 'admin',
            'cedula_profesional' => null,
            'universidad' => null,
        ]);
    }

    /**
     * Da el estado "contraseña temporal pendiente de cambiar".
     *
     * @return static fabrica configurada con is_temporary_password = true
     */
    public function conPasswordTemporal(): static
    {
        return $this->state(fn () => ['is_temporary_password' => true]);
    }

    /**
     * Da el estado "cuenta inactiva" (no puede iniciar sesion).
     *
     * @return static fabrica configurada con estado = inactivo
     */
    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => 'inactivo']);
    }
}
