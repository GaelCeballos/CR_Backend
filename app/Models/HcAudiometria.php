<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Umbral auditivo (en dB) de un oido en una frecuencia especifica. Cada historia
 * clinica tiene hasta 18 filas de este tipo (2 oidos x 9 frecuencias).
 */
#[Fillable(['historia_id', 'oido', 'frecuencia_hz', 'umbral_db'])]
class HcAudiometria extends Model
{
    protected $table = 'hc_audiometria';

    /** Llave compuesta (historia_id, oido, frecuencia_hz): ver nota en HcHistoriaLaboral. */
    public $incrementing = false;

    public $timestamps = false;

    /**
     * Historia clinica a la que pertenece esta medicion de audiometria.
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
