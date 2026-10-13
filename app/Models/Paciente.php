<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Identidad estable de un paciente (nombre, CURP, NSS, fecha de nacimiento, genero, origen).
 * Lo que cambia entre visitas (empresa, puesto, telefono, signos...) vive en HistoriaClinica.
 */
#[Fillable(['nombre_completo', 'curp', 'nss', 'fecha_nacimiento', 'genero', 'originario'])]
class Paciente extends Model
{
    use HasUuids;

    /**
     * Castea fecha_nacimiento a un objeto de fecha.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    /**
     * Domicilios registrados para este paciente (uno por cada vez que cambio).
     */
    public function domicilios()
    {
        return $this->hasMany(Domicilio::class);
    }

    /**
     * Historias clinicas (visitas) de este paciente.
     */
    public function historias()
    {
        return $this->hasMany(HistoriaClinica::class);
    }
}
