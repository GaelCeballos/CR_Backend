<?php

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de usuario (administrador o servicios medicos) que puede autenticarse en la API.
 */
#[Fillable([
    'email', 'hashed_password', 'nombre_completo', 'cedula_profesional', 'universidad',
    'rol', 'estado', 'is_temporary_password', 'creado_por',
])]
#[Hidden(['hashed_password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    /**
     * Castea rol/estado a sus enums, is_temporary_password a booleano y ultimo_acceso a fecha.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'estado' => EstadoUsuario::class,
            'is_temporary_password' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    /**
     * Columna real que guarda el hash de la contraseña (no se llama "password").
     *
     * @return string hash de la contraseña del usuario
     */
    public function getAuthPassword(): string
    {
        return $this->hashed_password;
    }

    /**
     * Administrador que creo esta cuenta (null si es la cuenta inicial del sistema).
     */
    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Historias clinicas donde este usuario es el medico que atiende.
     */
    public function historiasAtendidas()
    {
        return $this->hasMany(HistoriaClinica::class, 'medico_id');
    }

    /**
     * Historias clinicas que este usuario creo.
     */
    public function historiasCreadas()
    {
        return $this->hasMany(HistoriaClinica::class, 'creado_por');
    }

    /**
     * Historias clinicas que este usuario edito por ultima vez.
     */
    public function historiasEditadas()
    {
        return $this->hasMany(HistoriaClinica::class, 'actualizado_por');
    }

    /**
     * Eventos de bitacora generados por este usuario.
     */
    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'usuario_id');
    }
}
