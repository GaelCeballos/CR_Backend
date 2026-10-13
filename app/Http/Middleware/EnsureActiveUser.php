<?php

namespace App\Http\Middleware;

use App\Enums\EstadoUsuario;
use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;

/**
 * Bloquea cualquier endpoint si la cuenta autenticada esta inactiva, aunque su
 * token siga vigente.
 */
class EnsureActiveUser
{
    /**
     * @param  Request  $request  peticion entrante, ya autenticada por auth:sanctum
     * @param  Closure  $next  siguiente paso del pipeline de middleware
     * @return mixed la respuesta del siguiente middleware/controlador, o un error 403
     */
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if ($usuario && $usuario->estado === EstadoUsuario::Inactivo) {
            throw new ForbiddenException('Tu cuenta está inactiva. Contacta a Administración.');
        }

        return $next($request);
    }
}
