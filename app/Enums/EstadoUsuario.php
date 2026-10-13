<?php

namespace App\Enums;

/**
 * Estado de una cuenta de usuario: una cuenta inactiva no puede iniciar sesion.
 */
enum EstadoUsuario: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
}
