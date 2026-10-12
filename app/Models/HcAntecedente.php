<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'historia_id',
    'ahf_diabetes', 'ahf_hipertension', 'ahf_cardiopatia', 'ahf_neoplasias', 'ahf_infecciosa',
    'ahf_otras', 'ahf_abuelos_paternos', 'ahf_abuelos_maternos', 'ahf_padre', 'ahf_madre',
    'ahf_hermanos', 'ahf_hijos',
    'escolaridad', 'vivienda', 'alimentacion', 'convivencia_animales', 'actividad_fisica',
    'inmunizaciones', 'tatuajes',
    'enfermedades_profesionales',
    'etilismo', 'tabaquismo', 'adicciones', 'alergias', 'quirurgicos', 'traumatismos', 'hemotransfusiones',
    'app_hta', 'app_dm', 'app_cardiaca', 'app_respiratoria', 'app_renal',
    'app_musculo_esqueletica', 'app_lumbar', 'app_acido_peptica', 'app_otras',
])]
class HcAntecedente extends Model
{
    protected $table = 'hc_antecedentes';

    protected $primaryKey = 'historia_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
