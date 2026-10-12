<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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

    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
