<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un trabajo previo del paciente (actual, penultimo o antepenultimo). Cada historia
 * clinica tiene hasta 3 filas de este tipo, una por cada valor de "orden" (1, 2, 3).
 */
#[Fillable(['historia_id', 'orden', 'empresa', 'puesto', 'tiempo', 'condiciones'])]
class HcHistoriaLaboral extends Model
{
    protected $table = 'hc_historia_laboral';

    /**
     * Llave compuesta (historia_id, orden): Eloquent no soporta llaves primarias
     * compuestas, por eso los servicios borran e insertan las filas en vez de
     * actualizarlas una por una.
     */
    public $incrementing = false;

    public $timestamps = false;

    /**
     * Historia clinica a la que pertenece este trabajo previo.
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
