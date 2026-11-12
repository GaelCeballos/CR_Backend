<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoUsuario;
use App\Enums\RolUsuario;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SesionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Inicio y cierre de sesion, y consulta del usuario autenticado actual.
 */
class AuthController extends Controller
{
    /**
     * Post: valida correo/contraseña/perfil en el orden exacto requerido, revoca
     * cualquier token anterior del usuario y emite uno nuevo (sesion unica).
     *
     * @param  LoginRequest  $request  correo, contraseña y perfil elegido ("Ingresar como")
     * @return JsonResponse usuario autenticado, token y ruta de redireccion
     */
    public function login(LoginRequest $request, SesionService $sesiones)
    {
        $data = $request->validated();

        $usuario = User::where('email', strtolower($data['email']))->first();

        if (! $usuario || ! Hash::check($data['password'], $usuario->hashed_password)) {
            throw new InvalidCredentialsException;
        }

        if ($usuario->estado === EstadoUsuario::Inactivo) {
            throw new ForbiddenException('Tu cuenta está inactiva. Contacta a Administración.');
        }

        $perfil = RolUsuario::from($data['perfil']);

        if ($perfil === RolUsuario::Admin && $usuario->rol === RolUsuario::ServiciosMedicos) {
            throw new ForbiddenException('Esta cuenta no tiene acceso de Administración. Ingresa como Servicios Médicos.');
        }

        if ($perfil === RolUsuario::ServiciosMedicos && $usuario->rol === RolUsuario::Admin) {
            throw new ForbiddenException('Esta es una cuenta de Administración. Ingresa como Administración.');
        }

        $usuario->update(['ultimo_acceso' => now()]);

        // Si el usuario olvido cerrar sesion en otro equipo, este login invalida ese token.
        $token = $sesiones->iniciar($usuario);

        return response()->json([
            'user' => new UserResource($usuario),
            'token' => $token,
            'llave_borrador' => $sesiones->llaveBorrador($usuario),
            'redirect' => $usuario->is_temporary_password ? '/perfil' : '/expedientes',
        ]);
    }

    /**
     * Post: destruye unicamente el token con el que se hizo esta peticion.
     *
     * @param  Request  $request  peticion autenticada con el token a revocar
     * @return Response respuesta vacia (204)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Get: devuelve los datos del usuario que esta autenticado en esta peticion.
     *
     * @param  Request  $request  peticion autenticada
     * @return JsonResponse datos del usuario actual
     */
    public function me(Request $request, SesionService $sesiones)
    {
        return response()->json([
            'user' => new UserResource($request->user()),
            'llave_borrador' => $sesiones->llaveBorrador($request->user()),
        ]);
    }

    /**
     * Get: revision periodica del front. Confirma que la sesion sigue vigente y
     * dice cuando vence, sin contarse como actividad (no la extiende).
     *
     * @param  Request  $request  peticion autenticada
     * @return JsonResponse { vence_en } en ISO 8601
     */
    public function sesion(Request $request, SesionService $sesiones)
    {
        return response()->json([
            'vence_en' => $sesiones->venceEn($request->user()->currentAccessToken())->toIso8601String(),
        ]);
    }
}
