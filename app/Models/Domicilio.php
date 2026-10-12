<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['paciente_id', 'calle', 'colonia', 'municipio', 'estado'])]
class Domicilio extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function historias()
    {
        return $this->hasMany(HistoriaClinica::class);
    }
}
