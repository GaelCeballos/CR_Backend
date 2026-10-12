<?php

namespace App\Models;

use App\Enums\EstadoUsuario;
use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'email', 'hashed_password', 'nombre_completo', 'cedula_profesional', 'universidad',
    'rol', 'estado', 'is_temporary_password', 'creado_por',
])]
#[Hidden(['hashed_password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'estado' => EstadoUsuario::class,
            'is_temporary_password' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->hashed_password;
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function historiasAtendidas()
    {
        return $this->hasMany(HistoriaClinica::class, 'medico_id');
    }

    public function historiasCreadas()
    {
        return $this->hasMany(HistoriaClinica::class, 'creado_por');
    }

    public function historiasEditadas()
    {
        return $this->hasMany(HistoriaClinica::class, 'actualizado_por');
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'usuario_id');
    }
}
