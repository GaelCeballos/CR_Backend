<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resultados de laboratorio de una historia clinica: quimica sanguinea, biometria
 * hematica, antidoping y examen general de orina (relacion 1 a 1).
 */
#[Fillable([
    'historia_id',
    'qs_glucosa', 'qs_urea', 'qs_creatinina', 'qs_acido_urico', 'qs_colesterol', 'qs_trigliceridos',
    'qs_albumina', 'qs_bilirrubina_total', 'qs_ast', 'qs_fosfatasa_alcalina', 'qs_dhl', 'qs_hba1c',
    'bh_grupo_rh', 'bh_leucocitos', 'bh_eritrocitos', 'bh_hemoglobina', 'bh_hematocrito', 'bh_vcm',
    'bh_mch', 'bh_mchc', 'bh_plaquetas', 'bh_mpv', 'bh_neutrofilos', 'bh_linfocitos_pct',
    'bh_monocitos', 'bh_linfocitos_abs',
    'ad_opi', 'ad_coc', 'ad_thc', 'ad_bzo', 'ad_met', 'ad_amp',
    'ego_color', 'ego_aspecto', 'ego_densidad', 'ego_ph', 'ego_nitritos', 'ego_proteinas',
    'ego_glucosa', 'ego_cetonas', 'ego_bilirrubina', 'ego_urobilinogeno', 'ego_hemoglobina',
    'ego_leucocitos', 'ego_eritrocitos', 'ego_cristales',
])]
class HcLaboratorio extends Model
{
    protected $table = 'hc_laboratorio';

    protected $primaryKey = 'historia_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Historia clinica a la que pertenecen estos resultados de laboratorio.
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
