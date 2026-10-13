<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resultados de gabinete de una historia clinica: radiografias, audiometria resumida,
 * ECG, espirometria y valoracion oftalmologica (relacion 1 a 1).
 */
#[Fillable([
    'historia_id', 'rx_torax', 'rx_lumbar', 'audiometria_resultado',
    'ecg', 'espirometria', 'valoracion_oftalmologica',
])]
class HcGabinete extends Model
{
    protected $table = 'hc_gabinete';

    protected $primaryKey = 'historia_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Historia clinica a la que pertenecen estos resultados de gabinete.
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
