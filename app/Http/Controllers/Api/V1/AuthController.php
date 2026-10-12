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
use Illuminate\Http\Request;
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
     * @return \Illuminate\Http\JsonResponse usuario autenticado, token y ruta de redireccion
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $usuario = User::where('email', strtolower($data['email']))->first();

        if (! $usuario || ! Hash::check($data['password'], $usuario->hashed_password)) {
            throw new InvalidCredentialsException();
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
        $usuario->tokens()->delete();

        $token = $usuario->createToken(
            'api',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration')),
        )->plainTextToken;

        return response()->json([
            'user' => new UserResource($usuario),
            'token' => $token,
            'redirect' => $usuario->is_temporary_password ? '/perfil' : '/expedientes',
        ]);
    }

    /**
     * Post: destruye unicamente el token con el que se hizo esta peticion.
     *
     * @param  Request  $request  peticion autenticada con el token a revocar
     * @return \Illuminate\Http\Response respuesta vacia (204)
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
     * @return \Illuminate\Http\JsonResponse datos del usuario actual
     */
    public function me(Request $request)
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }
}
