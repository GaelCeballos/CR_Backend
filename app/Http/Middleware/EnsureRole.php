<?php

namespace App\Http\Middleware;

use App\Enums\RolUsuario;
use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;

/**
 * Matriz de permisos por rol (spec §1.3): solo admin puede usar las rutas marcadas con este middleware.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $rol)
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->rol !== RolUsuario::from($rol)) {
            throw new ForbiddenException('No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
