<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['historia_id', 'orden', 'empresa', 'puesto', 'tiempo', 'condiciones'])]
class HcHistoriaLaboral extends Model
{
    protected $table = 'hc_historia_laboral';

    // Clave compuesta (historia_id, orden): Eloquent no soporta llaves primarias
    // compuestas, por eso los servicios reemplazan las filas en vez de actualizarlas.
    public $incrementing = false;

    public $timestamps = false;

    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
