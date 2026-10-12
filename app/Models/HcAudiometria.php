<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['historia_id', 'oido', 'frecuencia_hz', 'umbral_db'])]
class HcAudiometria extends Model
{
    protected $table = 'hc_audiometria';

    // Clave compuesta (historia_id, oido, frecuencia_hz): ver nota en HcHistoriaLaboral.
    public $incrementing = false;

    public $timestamps = false;

    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
