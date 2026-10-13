<?php

namespace App\Http\Middleware;

use App\Enums\RolUsuario;
use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;

/**
 * Deja pasar la peticion solo si el usuario autenticado tiene el rol indicado
 * en la ruta (p. ej. ->middleware('role:admin')).
 */
class EnsureRole
{
    /**
     * @param  Request  $request  peticion entrante, ya autenticada por auth:sanctum
     * @param  Closure  $next  siguiente paso del pipeline de middleware
     * @param  string  $rol  valor del enum RolUsuario requerido para continuar
     * @return mixed la respuesta del siguiente middleware/controlador, o un error 403
     */
    public function handle(Request $request, Closure $next, string $rol)
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->rol !== RolUsuario::from($rol)) {
            throw new ForbiddenException('No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
