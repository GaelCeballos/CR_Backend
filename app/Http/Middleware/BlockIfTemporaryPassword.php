<?php

namespace App\Http\Middleware;

use App\Exceptions\TemporaryPasswordException;
use Closure;
use Illuminate\Http\Request;

/**
 * Bloquea el acceso a cualquier ruta que use este middleware si la cuenta
 * autenticada todavia tiene una contraseña temporal pendiente de cambiar.
 * Las rutas que deben seguir disponibles en ese caso (/me, /me/password,
 * /auth/logout, /auth/me) simplemente no se agrupan con este middleware
 * en routes/api.php.
 */
class BlockIfTemporaryPassword
{
    /**
     * @param  Request  $request  peticion entrante, ya autenticada por auth:sanctum
     * @param  Closure  $next  siguiente paso del pipeline de middleware
     * @return mixed la respuesta del siguiente middleware/controlador, o un error 403
     */
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if ($usuario && $usuario->is_temporary_password) {
            throw new TemporaryPasswordException();
        }

        return $next($request);
    }
}
