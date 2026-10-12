<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Domicilio de un paciente en un momento dado. Si el domicilio cambia se inserta una
 * fila nueva; las historias clinicas anteriores siguen apuntando a la fila original.
 */
#[Fillable(['paciente_id', 'calle', 'colonia', 'municipio', 'estado'])]
class Domicilio extends Model
{
    use HasUuids;

    /** La tabla solo tiene created_at, no updated_at (es una instantanea, no se edita). */
    const UPDATED_AT = null;

    /**
     * Paciente dueño de este domicilio.
     */
    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * Historias clinicas que usaron este domicilio.
     */
    public function historias()
    {
        return $this->hasMany(HistoriaClinica::class);
    }
}
