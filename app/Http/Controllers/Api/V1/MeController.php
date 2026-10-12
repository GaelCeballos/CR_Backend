<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Edicion del perfil propio: datos personales y cambio de contraseña.
 */
class MeController extends Controller
{
    /**
     * Put: actualiza nombre, correo, cedula y universidad del usuario autenticado.
     * El rol nunca se toma de este formulario, siempre queda igual.
     *
     * @param  UpdateMeRequest  $request  datos nuevos del perfil, ya validados
     * @return \Illuminate\Http\JsonResponse usuario actualizado
     */
    public function update(UpdateMeRequest $request)
    {
        $usuario = $request->user();
        $data = $request->validated();

        $email = strtolower($data['email']);

        if (User::where('email', $email)->where('id', '!=', $usuario->id)->exists()) {
            throw new ConflictException('Ese correo ya lo usa otra cuenta.', ['email' => 'Ese correo ya lo usa otra cuenta.']);
        }

        $usuario->update([
            'nombre_completo' => mb_strtoupper($data['nombre_completo']),
            'email' => $email,
            'cedula_profesional' => $data['cedula_profesional'] ?? null,
            'universidad' => isset($data['universidad']) ? mb_strtoupper($data['universidad']) : null,
        ]);

        return response()->json(['user' => new UserResource($usuario)]);
    }

    /**
     * Post: cambia la contraseña del usuario autenticado y apaga la marca de
     * contraseña temporal.
     *
     * @param  ChangePasswordRequest  $request  contraseña actual y nueva, ya validadas
     * @return \Illuminate\Http\JsonResponse usuario actualizado
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $usuario = $request->user();

        $usuario->update([
            'hashed_password' => Hash::make($request->validated('nueva')),
            'is_temporary_password' => false,
        ]);

        return response()->json(['user' => new UserResource($usuario)]);
    }
}
