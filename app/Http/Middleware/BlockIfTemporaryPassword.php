<?php

namespace App\Http\Middleware;

use App\Exceptions\TemporaryPasswordException;
use Closure;
use Illuminate\Http\Request;

/**
 * Mientras la contraseña sea temporal, solo /me, /me/password, /auth/logout y /auth/me quedan
 * disponibles (spec §3.2) — ese alcance lo decide routes/api.php al no aplicar este middleware
 * a esas rutas, no esta clase.
 */
class BlockIfTemporaryPassword
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if ($usuario && $usuario->is_temporary_password) {
            throw new TemporaryPasswordException();
        }

        return $next($request);
    }
}
