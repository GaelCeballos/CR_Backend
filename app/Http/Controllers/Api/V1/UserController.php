<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoUsuario;
use App\Enums\RolUsuario;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Alta, listado, consulta y edicion de cuentas de usuario. Todo este
 * controlador es exclusivo del rol admin (ver middleware de la ruta), salvo
 * la vista "selector" del listado, que cualquier usuario autenticado puede leer.
 */
class UserController extends Controller
{
    /**
     * Post: crea una cuenta nueva con una contraseña temporal. Si no se envia
     * una contraseña, el servidor genera una.
     *
     * @param  StoreUserRequest  $request  datos de la cuenta nueva, ya validados
     * @param  PasswordGeneratorService  $passwords  genera la contraseña temporal cuando falta
     * @return \Illuminate\Http\JsonResponse la cuenta creada y su contraseña temporal
     */
    public function store(StoreUserRequest $request, PasswordGeneratorService $passwords)
    {
        $data = $request->validated();
        $email = strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            throw new ConflictException('Ya existe una cuenta con ese correo.', ['email' => 'Ya existe una cuenta con ese correo.']);
        }

        $temporaryPassword = $data['password'] ?? $passwords->generar();

        $usuario = User::create([
            'email' => $email,
            'hashed_password' => Hash::make($temporaryPassword),
            'nombre_completo' => mb_strtoupper($data['nombre_completo']),
            'rol' => $data['rol'],
            'estado' => 'activo',
            'cedula_profesional' => $data['cedula_profesional'] ?? null,
            'universidad' => isset($data['universidad']) ? mb_strtoupper($data['universidad']) : null,
            'is_temporary_password' => true,
            'creado_por' => $request->user()->id,
        ]);

        return response()->json([
            'user' => new UserResource($usuario),
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    /**
     * Get: lista las cuentas. Con ?vista=selector devuelve solo los medicos
     * activos con cedula y universidad (para el selector del formulario de
     * expedientes); sin ese parametro, da la tabla completa de administracion.
     *
     * @param  Request  $request  filtros opcionales: q, rol, estado, vista
     * @return \Illuminate\Http\JsonResponse lista de usuarios segun la vista pedida
     */
    public function index(Request $request)
    {
        if ($request->query('vista') === 'selector') {
            return response()->json([
                'items' => DB::table('medicos_selector')->orderBy('nombre_completo')->get(),
            ]);
        }

        if ($request->user()->rol !== RolUsuario::Admin) {
            throw new ForbiddenException('No tienes permiso para esta acción.');
        }

        $query = User::query();

        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('nombre_completo', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%"));
        }

        if ($rol = $request->query('rol')) {
            $query->where('rol', $rol);
        }

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        $usuarios = $query->orderByDesc('created_at')->get();

        return response()->json(['items' => UserResource::collection($usuarios)]);
    }

    /**
     * Get: detalle de una cuenta, incluyendo el nombre de quien la creo.
     *
     * @param  User  $user  cuenta a consultar (enlace de ruta implicito)
     * @return \Illuminate\Http\JsonResponse datos completos de la cuenta
     */
    public function show(User $user)
    {
        return response()->json(['user' => new UserResource($user->load('creadoPor'))]);
    }

    /**
     * Put: edita una cuenta existente. Aplica las salvaguardas: nadie puede
     * desactivarse ni quitarse el rol de admin a si mismo, y siempre debe
     * quedar al menos un administrador activo en el sistema.
     *
     * @param  UpdateUserRequest  $request  datos nuevos de la cuenta, ya validados
     * @param  User  $user  cuenta a editar (enlace de ruta implicito)
     * @return \Illuminate\Http\JsonResponse cuenta actualizada
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $autenticado = $request->user();
        $esUnoMismo = $autenticado->id === $user->id;

        if ($esUnoMismo && $data['estado'] === EstadoUsuario::Inactivo->value) {
            throw new ForbiddenException('No puedes desactivar tu propia cuenta.');
        }

        if ($esUnoMismo && $user->rol === RolUsuario::Admin && $data['rol'] !== RolUsuario::Admin->value) {
            throw new ForbiddenException('No puedes quitarte el rol de Administrador a ti mismo.');
        }

        $dejaDeSerAdminActivo = $user->rol === RolUsuario::Admin
            && ($data['rol'] !== RolUsuario::Admin->value || $data['estado'] === EstadoUsuario::Inactivo->value);

        if ($dejaDeSerAdminActivo) {
            $otrosAdminsActivos = User::where('id', '!=', $user->id)
                ->where('rol', RolUsuario::Admin->value)
                ->where('estado', EstadoUsuario::Activo->value)
                ->exists();

            if (! $otrosAdminsActivos) {
                throw new ConflictException('Debe quedar al menos un Administrador activo.');
            }
        }

        $email = strtolower($data['email']);

        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            throw new ConflictException('Ese correo ya lo usa otra cuenta.', ['email' => 'Ese correo ya lo usa otra cuenta.']);
        }

        $user->update([
            'nombre_completo' => mb_strtoupper($data['nombre_completo']),
            'email' => $email,
            'rol' => $data['rol'],
            'cedula_profesional' => $data['cedula_profesional'] ?? null,
            'universidad' => isset($data['universidad']) ? mb_strtoupper($data['universidad']) : null,
            'estado' => $data['estado'],
        ]);

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * Post: genera una nueva contraseña temporal para la cuenta indicada y
     * revoca de inmediato cualquier sesion abierta con la contraseña anterior.
     *
     * @param  User  $user  cuenta a la que se le restablece la contraseña
     * @param  PasswordGeneratorService  $passwords  genera la nueva contraseña temporal
     * @return \Illuminate\Http\JsonResponse la nueva contraseña temporal
     */
    public function resetPassword(User $user, PasswordGeneratorService $passwords)
    {
        $temporaryPassword = $passwords->generar();

        $user->update([
            'hashed_password' => Hash::make($temporaryPassword),
            'is_temporary_password' => true,
        ]);

        $user->tokens()->delete();

        return response()->json(['temporary_password' => $temporaryPassword]);
    }
}
