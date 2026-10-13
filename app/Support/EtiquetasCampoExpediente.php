<?php

namespace App\Support;

/**
 * Traduce cada columna del expediente a su código y etiqueta oficial (por
 * ejemplo "082 Peso"), para armar el detalle legible de la bitácora cuando se
 * edita un expediente.
 */
final class EtiquetasCampoExpediente
{
    /**
     * @var array<string, string> etiqueta oficial por columna/clave de frontend
     */
    private const ETIQUETAS = [
        'nombre_completo' => '003 Nombre completo',
        'puesto' => '004 Puesto',
        'empresa' => '005 Empresa (tarjeta de acceso)',
        'zona_trabajo' => '006 Zona de trabajo',
        'tipo_examen_motivo' => '007 Motivo del Examen',
        'antiguedad_empresa' => '008 Antigüedad en la empresa',
        'antiguedad_puesto' => '009 Antigüedad en el puesto',
        'exposicion_poe' => '010 Exposición (POE)',
        'nss' => '011 IMSS NSS',
        'curp' => '012 CURP',
        'genero' => '013 Género',
        'area' => '014 Área',
        'tipo_examen_categoria' => '015 Categoría del Examen / Puesto',
        'fecha_nacimiento' => '016 Fecha de nacimiento',
        'domicilio_calle' => '019 Calle y número',
        'domicilio_colonia' => '020 Colonia',
        'domicilio_municipio' => '021 Municipio',
        'domicilio_estado' => '022 Estado',
        'telefono' => '023 Teléfono',
        'originario' => '024 Originario (estado)',
        'religion' => '025 Religión',
        'estado_civil' => '026 Estado civil',
        'ahf_diabetes' => '027 Diabetes',
        'ahf_hipertension' => '028 Hipertensión',
        'ahf_cardiopatia' => '029 Cardiopatía',
        'ahf_neoplasias' => '030 Neoplasias',
        'ahf_infecciosa' => '031 Infecciosa',
        'ahf_otras' => '032 Otras',
        'ahf_abuelos_paternos' => '033 Abuelos paternos',
        'ahf_abuelos_maternos' => '034 Abuelos maternos',
        'ahf_padre' => '035 Padre',
        'ahf_madre' => '036 Madre',
        'ahf_hermanos' => '037 Hermanos',
        'ahf_hijos' => '038 Hijos',
        'escolaridad' => '039 Escolaridad',
        'vivienda' => '040 Vivienda (materiales de construcción)',
        'alimentacion' => '041 Alimentación (calidad / frecuencia)',
        'convivencia_animales' => '042 Convivencia con animales',
        'actividad_fisica' => '043 Actividad física',
        'inmunizaciones' => '044 Inmunizaciones (esquema de vacunación)',
        'tatuajes' => '045 Tatuajes',
        'enfermedades_profesionales' => '058 Enfermedades profesionales y/o secuelas',
        'etilismo' => '059 Etilismo (frecuencia / cantidad)',
        'tabaquismo' => '060 Tabaquismo (frecuencia / cantidad)',
        'adicciones' => '061 Adicciones',
        'alergias' => '062 Alergias',
        'quirurgicos' => '063 Quirúrgicos',
        'traumatismos' => '064 Traumatismos',
        'hemotransfusiones' => '065 Hemotransfusiones',
        'app_hta' => '066 Hipertensión arterial',
        'app_dm' => '067 Diabetes mellitus',
        'app_cardiaca' => '068 Patología cardíaca',
        'app_respiratoria' => '069 Patología respiratoria',
        'app_renal' => '070 Patología renal',
        'app_musculo_esqueletica' => '071 Patología músculo-esquelética',
        'app_lumbar' => '072 Patología lumbar',
        'app_acido_peptica' => '073 Enfermedad ácido péptica',
        'app_otras' => '074 Otras (especificar)',
        'pulso' => '075 Pulso',
        'respiracion' => '076 Respiración',
        'temperatura' => '077 Temperatura',
        'ta_sistolica' => '078 TA sistólica',
        'ta_diastolica' => '079 TA diastólica',
        'clasificacion_aha' => '081 Clasificación de T/A',
        'peso' => '082 Peso',
        'talla' => '083 Talla',
        'constitucion' => '086 Tipo',
        'piel_faneras' => '087 Piel(Aspecto)',
        'torax_obs' => '088 Tórax: observaciones',
        'torax_pulmones' => '089 Tórax: pulmones',
        'torax_corazon' => '090 Tórax: corazón',
        'abdomen_obs' => '091 Abdomen: observaciones',
        'abdomen_higado' => '092 Abdomen: hígado',
        'abdomen_hernias' => '093 Abdomen: hernias',
        'abdomen_hemorroides' => '094 Abdomen: hemorroides',
        'abdomen_bazo' => '095 Abdomen: bazo',
        'vl_od' => '096 Visión lejana (Snellen) OD',
        'vl_oi' => '097 Visión lejana (Snellen) OI',
        'vl_con_lentes' => '098 Visión lejana: examen con lentes',
        'vl_resultado' => '099 Visión lejana: resultado',
        'vc_od' => '100 Visión cercana (Jaeger) OD',
        'vc_oi' => '101 Visión cercana (Jaeger) OI',
        'vc_con_lentes' => '102 Visión cercana: examen con lentes',
        'vc_resultado' => '103 Visión cercana: resultado',
        'reflejos_oculares_od' => '104 Reflejos oculares OD',
        'reflejos_oculares_oi' => '105 Reflejos oculares OI',
        'pterigion_od' => '106 Pterigiones OD',
        'pterigion_oi' => '107 Pterigiones OI',
        'catarata_od' => '108 Cataratas OD',
        'catarata_oi' => '109 Cataratas OI',
        'estrabismo' => '110 Estrabismo',
        'ishihara' => '111 Diferenciación de colores (Ishihara)',
        'oftal_recomendaciones' => '112 Oftalmología: recomendaciones',
        'lentes_seguridad' => '113 Lentes de seguridad graduados',
        'canal_auditivo_od' => '114 Canal auditivo: oído derecho',
        'canal_auditivo_oi' => '115 Canal auditivo: oído izquierdo',
        'membrana_timpanica_d' => '116 Membrana timpánica derecha',
        'membrana_timpanica_i' => '117 Membrana timpánica izquierda',
        'nariz_obs' => '118 Nariz: observaciones',
        'nariz_obstrucciones' => '119 Nariz: obstrucciones',
        'orofaringe' => '120 Orofaringe: observaciones',
        'dentadura' => '121 Dentadura',
        'cuello' => '122 Cuello: observaciones',
        'gu_obs' => '123 Genitourinario: observaciones',
        'gu_hernias' => '124 Genitourinario: hernias',
        'gu_hidrocele' => '125 Genitourinario: hidrocele',
        'gu_varicocele' => '126 Genitourinario: varicocele',
        'ext_sup_obs' => '127 Extremidades superiores: observaciones',
        'ext_sup_manos' => '128 Extremidades superiores: manos',
        'ext_inf_obs' => '129 Extremidades inferiores: observaciones',
        'ext_inf_hallux_valgus' => '130 Extremidades inferiores: hallux valgus',
        'reflejos_osteotendinosos' => '131 Reflejos osteotendinosos',
        'psiquismo' => '132 Psiquismo',
        'padecimiento_actual' => '133 Padecimiento actual / toma de medicamentos',
        'qs_glucosa' => '134 Glucosa',
        'qs_urea' => '135 Urea',
        'qs_creatinina' => '136 Creatinina',
        'qs_acido_urico' => '137 Ácido úrico',
        'qs_colesterol' => '138 Colesterol total',
        'qs_trigliceridos' => '139 Triglicéridos',
        'qs_albumina' => '140 Albúmina',
        'qs_bilirrubina_total' => '141 Bilirrubina total',
        'qs_ast' => '142 AST (TGO)',
        'qs_fosfatasa_alcalina' => '143 Fosfatasa alcalina',
        'qs_dhl' => '144 DHL',
        'qs_hba1c' => '145 HbA1c',
        'bh_grupo_rh' => '146 Grupo sanguíneo y Rh',
        'bh_leucocitos' => '147 Leucocitos',
        'bh_eritrocitos' => '148 Eritrocitos',
        'bh_hemoglobina' => '149 Hemoglobina',
        'bh_hematocrito' => '150 Hematocrito',
        'bh_vcm' => '151 VCM',
        'bh_mch' => '152 MCH',
        'bh_mchc' => '153 MCHC',
        'bh_plaquetas' => '154 Plaquetas',
        'bh_mpv' => '155 MPV',
        'bh_neutrofilos' => '156 Neutrófilos (%)',
        'bh_linfocitos_pct' => '157 Linfocitos (%)',
        'bh_monocitos' => '158 Monocitos (%)',
        'bh_linfocitos_abs' => '159 Linfocitos (absoluto)',
        'ad_opi' => '160 Opiáceos (OPI)',
        'ad_coc' => '161 Cocaína (COC)',
        'ad_thc' => '162 Cannabinoides (THC)',
        'ad_bzo' => '163 Benzodiacepinas (BZO)',
        'ad_met' => '164 Metanfetaminas (MET)',
        'ad_amp' => '165 Anfetaminas (AMP)',
        'ego_color' => '166 Color',
        'ego_aspecto' => '167 Aspecto',
        'ego_densidad' => '168 Densidad',
        'ego_ph' => '169 pH',
        'ego_nitritos' => '170 Nitritos',
        'ego_proteinas' => '171 Proteínas',
        'ego_glucosa' => '172 Glucosa',
        'ego_cetonas' => '173 Cetonas',
        'ego_bilirrubina' => '174 Bilirrubina',
        'ego_urobilinogeno' => '175 Urobilinógeno',
        'ego_hemoglobina' => '176 Hemoglobina',
        'ego_leucocitos' => '177 Leucocitos',
        'ego_eritrocitos' => '178 Eritrocitos',
        'ego_cristales' => '179 Cristales',
        'rx_torax' => '180 Radiografía de tórax (interpretación)',
        'rx_lumbar' => '181 Radiografía lumbar (interpretación)',
        'audiometria_resultado' => '200 Resultado / diagnóstico auditivo',
        'ecg' => '201 Electrocardiograma (ECG / PF)',
        'espirometria' => '202 Espirometría (interpretación)',
        'valoracion_oftalmologica' => '203 Valoración oftalmológica especializada',
        'diagnostico' => '204 Diagnóstico general / impresión diagnóstica',
        'gradiente' => '205 Gradiente (evaluación global de aptitud)',
        'medico_dictaminador' => '206 Médico dictaminador / médico que atiende',
        'fecha_entrega_resultados' => 'R1 Fecha de entrega de resultados',
        'fecha_siguiente_valoracion' => 'R2 Fecha de siguiente valoración',
    ];

    /**
     * @var array<int, array{codigo: int, sufijo: string}> codigos 046-057 para los 3 trabajos previos
     */
    private const CODIGOS_HISTORIA_LABORAL = [
        't_actual_' => 46,
        't_penultimo_' => 50,
        't_antepenultimo_' => 54,
    ];

    /**
     * Da la etiqueta oficial ("082 Peso") de una columna/clave de frontend.
     * Si no esta en el catalogo (columnas calculadas, de auditoria o los
     * umbrales de audiometria/historia laboral), regresa la clave tal cual.
     *
     * @param  string  $clave  nombre de columna o clave de frontend
     * @return string la etiqueta oficial, o la misma clave si no se encontro
     */
    public static function etiqueta(string $clave): string
    {
        if (isset(self::ETIQUETAS[$clave])) {
            return self::ETIQUETAS[$clave];
        }

        foreach (self::CODIGOS_HISTORIA_LABORAL as $prefijo => $codigoBase) {
            if (str_starts_with($clave, $prefijo)) {
                $sufijo = substr($clave, strlen($prefijo));
                $orden = ['empresa' => 0, 'puesto' => 1, 'tiempo' => 2, 'condiciones' => 3][$sufijo] ?? null;

                if ($orden !== null) {
                    $etiqueta = ['Empresa', 'Puesto', 'Tiempo', 'Condiciones especiales'][$orden];

                    return sprintf('%03d %s', $codigoBase + $orden, $etiqueta);
                }
            }
        }

        if (preg_match('/^aud(\d+)_(od|oi)$/', $clave, $m) === 1) {
            $frecuencias = CamposExpediente::frecuenciasAudiometria();
            $indice = array_search((int) $m[1], $frecuencias, true);

            if ($indice !== false) {
                return sprintf('%03d %s Hz %s', 182 + $indice * 2 + ($m[2] === 'oi' ? 1 : 0), $m[1], mb_strtoupper($m[2]));
            }
        }

        return $clave;
    }
}
