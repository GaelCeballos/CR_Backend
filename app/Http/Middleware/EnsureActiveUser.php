<?php

namespace App\Http\Middleware;

use App\Enums\EstadoUsuario;
use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;

/**
 * Una cuenta inactiva no puede usar ningun endpoint, aunque su token siga vigente (spec §5.6).
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if ($usuario && $usuario->estado === EstadoUsuario::Inactivo) {
            throw new ForbiddenException('Tu cuenta está inactiva. Contacta a Administración.');
        }

        return $next($request);
    }
}
