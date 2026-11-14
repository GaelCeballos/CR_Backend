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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        // Si el correo no existe se verifica igual contra un hash señuelo: sin esto
        // la respuesta tardaba ~1 ms en vez de ~100 ms y revelaba que correos
        // tienen cuenta (auditoria run-1, H03).
        $hashValido = Hash::check($data['password'], $usuario?->hashed_password ?? $this->hashSenuelo());

        if (! $usuario || ! $hashValido) {
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

    /**
     * Hash de una contraseña aleatoria con la configuracion vigente de hashing,
     * para que verificar un correo inexistente cueste lo mismo que uno real.
     * Se guarda en cache con los parametros en la clave: si cambian ARGON_*,
     * se genera otro con el mismo costo que los hashes reales.
     *
     * @return string hash argon2id que ninguna contraseña real verifica
     */
    private function hashSenuelo(): string
    {
        $clave = 'login:hash-senuelo:'.md5((string) json_encode([config('hashing.driver'), config('hashing.argon')]));

        return Cache::rememberForever($clave, fn () => Hash::make(Str::random(40)));
    }
}
