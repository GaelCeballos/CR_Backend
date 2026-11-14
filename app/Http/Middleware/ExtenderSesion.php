<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Services\SesionService;
use Closure;
use Illuminate\Http\Request;

/**
 * Cuenta cada peticion autenticada como actividad y extiende la sesion.
 * GET /auth/sesion lo omite a proposito: es la revision periodica del front y,
 * si contara, una pestaña olvidada nunca venceria por inactividad.
 */
class ExtenderSesion
{
    public function __construct(private SesionService $sesiones) {}

    /**
     * @param  Request  $request  peticion ya autenticada por auth:sanctum
     * @param  Closure  $next  siguiente paso del pipeline
     * @return mixed respuesta del siguiente middleware/controlador
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $this->sesiones->extender($token);
        }

        return $next($request);
    }
}
