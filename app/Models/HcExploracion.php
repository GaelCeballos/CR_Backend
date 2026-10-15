<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Exploracion fisica completa de una historia clinica (signos vitales, aspecto
 * general, torax/abdomen, ojos, oidos, etc.) — relacion 1 a 1 con historias_clinicas.
 */
#[Fillable([
    'historia_id',
    'pulso', 'respiracion', 'temperatura', 'ta_sistolica', 'ta_diastolica', 'clasificacion_aha',
    'peso', 'talla', 'constitucion', 'piel_faneras',
    'torax_obs', 'torax_pulmones', 'torax_corazon', 'abdomen_obs', 'abdomen_higado',
    'abdomen_hernias', 'abdomen_hemorroides', 'abdomen_bazo',
    'vl_od', 'vl_oi', 'vl_con_lentes', 'vl_resultado', 'vc_od', 'vc_oi', 'vc_con_lentes', 'vc_resultado',
    'reflejos_oculares_od', 'reflejos_oculares_oi', 'pterigion_od', 'pterigion_oi',
    'catarata_od', 'catarata_oi', 'estrabismo', 'ishihara', 'oftal_recomendaciones', 'lentes_seguridad',
    'examenes_visuales_realizados',
    'canal_auditivo_od', 'canal_auditivo_oi', 'membrana_timpanica_d', 'membrana_timpanica_i',
    'nariz_obs', 'nariz_obstrucciones', 'orofaringe', 'dentadura', 'cuello',
    'gu_obs', 'gu_hernias', 'gu_hidrocele', 'gu_varicocele',
    'ext_sup_obs', 'ext_sup_manos', 'ext_inf_obs', 'ext_inf_hallux_valgus', 'reflejos_osteotendinosos',
    'psiquismo', 'padecimiento_actual',
    // No incluye ta_conjunta, imc ni estado_nutricional: Postgres las calcula solo
    // (columnas generadas) y la aplicacion nunca debe intentar escribirlas.
])]
class HcExploracion extends Model
{
    protected $table = 'hc_exploracion';

    protected $primaryKey = 'historia_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'examenes_visuales_realizados' => 'boolean',
    ];

    /**
     * Historia clinica a la que pertenece esta exploracion.
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
