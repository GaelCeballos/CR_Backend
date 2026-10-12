<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre_completo', 'curp', 'nss', 'fecha_nacimiento', 'genero', 'originario'])]
class Paciente extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    public function domicilios()
    {
        return $this->hasMany(Domicilio::class);
    }

    public function historias()
    {
        return $this->hasMany(HistoriaClinica::class);
    }
}
