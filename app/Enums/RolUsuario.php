<?php

namespace App\Enums;

/**
 * Rol de una cuenta de usuario.
 */
enum RolUsuario: string
{
    /** Acceso total a la API: usuarios, bitacora, exportaciones y expedientes. */
    case Admin = 'admin';

    /** Solo puede capturar/editar pacientes y expedientes, y su propio perfil. */
    case ServiciosMedicos = 'servicios_medicos';
}
