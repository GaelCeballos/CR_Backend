<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da la forma publica de un usuario para las respuestas JSON: nunca incluye
 * el hash de la contraseña ni otros datos internos del modelo.
 */
class UserResource extends JsonResource
{
    /**
     * @param  Request  $request  peticion HTTP actual
     * @return array<string, mixed> datos del usuario que se envian al cliente
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nombre_completo' => $this->nombre_completo,
            'rol' => $this->rol->value,
            'estado' => $this->estado->value,
            'cedula_profesional' => $this->cedula_profesional,
            'universidad' => $this->universidad,
            'is_temporary_password' => $this->is_temporary_password,
            'ultimo_acceso' => $this->ultimo_acceso?->toIso8601String(),
            'creado_por' => $this->creado_por,
            'creado_por_nombre' => $this->whenLoaded('creadoPor', fn () => $this->creadoPor?->nombre_completo),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
