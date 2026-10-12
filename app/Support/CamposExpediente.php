<?php

namespace App\Support;

/**
 * Listas de columnas de cada modulo clinico, usadas para repartir el payload
 * plano que envia el cliente entre las tablas normalizadas de la base de datos.
 */
final class CamposExpediente
{
    /**
     * @return array<int, string> columnas de pacientes que vienen del cliente
     */
    public static function paciente(): array
    {
        return ['nombre_completo', 'curp', 'nss', 'fecha_nacimiento', 'genero', 'originario'];
    }

    /**
     * @return array<int, string> columnas de historias_clinicas que vienen del cliente
     */
    public static function historia(): array
    {
        return [
            'puesto', 'empresa', 'zona_trabajo', 'tipo_examen_motivo', 'antiguedad_empresa',
            'antiguedad_puesto', 'exposicion_poe', 'area', 'tipo_examen_categoria',
            'telefono', 'religion', 'estado_civil', 'diagnostico', 'gradiente',
            'fecha_entrega_resultados',
        ];
    }

    /**
     * @return array<int, string> columnas de hc_antecedentes
     */
    public static function antecedentes(): array
    {
        return [
            'ahf_diabetes', 'ahf_hipertension', 'ahf_cardiopatia', 'ahf_neoplasias', 'ahf_infecciosa',
            'ahf_otras', 'ahf_abuelos_paternos', 'ahf_abuelos_maternos', 'ahf_padre', 'ahf_madre',
            'ahf_hermanos', 'ahf_hijos',
            'escolaridad', 'vivienda', 'alimentacion', 'convivencia_animales', 'actividad_fisica',
            'inmunizaciones', 'tatuajes',
            'enfermedades_profesionales',
            'etilismo', 'tabaquismo', 'adicciones', 'alergias', 'quirurgicos', 'traumatismos', 'hemotransfusiones',
            'app_hta', 'app_dm', 'app_cardiaca', 'app_respiratoria', 'app_renal',
            'app_musculo_esqueletica', 'app_lumbar', 'app_acido_peptica', 'app_otras',
        ];
    }

    /**
     * @return array<int, string> columnas de hc_exploracion que acepta el cliente
     *                             (no incluye ta_conjunta/imc/estado_nutricional: los calcula Postgres)
     */
    public static function exploracion(): array
    {
        return [
            'pulso', 'respiracion', 'temperatura', 'ta_sistolica', 'ta_diastolica', 'clasificacion_aha',
            'peso', 'talla', 'constitucion', 'piel_faneras',
            'torax_obs', 'torax_pulmones', 'torax_corazon', 'abdomen_obs', 'abdomen_higado',
            'abdomen_hernias', 'abdomen_hemorroides', 'abdomen_bazo',
            'vl_od', 'vl_oi', 'vl_con_lentes', 'vl_resultado', 'vc_od', 'vc_oi', 'vc_con_lentes', 'vc_resultado',
            'reflejos_oculares_od', 'reflejos_oculares_oi', 'pterigion_od', 'pterigion_oi',
            'catarata_od', 'catarata_oi', 'estrabismo', 'ishihara', 'oftal_recomendaciones', 'lentes_seguridad',
            'canal_auditivo_od', 'canal_auditivo_oi', 'membrana_timpanica_d', 'membrana_timpanica_i',
            'nariz_obs', 'nariz_obstrucciones', 'orofaringe', 'dentadura', 'cuello',
            'gu_obs', 'gu_hernias', 'gu_hidrocele', 'gu_varicocele',
            'ext_sup_obs', 'ext_sup_manos', 'ext_inf_obs', 'ext_inf_hallux_valgus', 'reflejos_osteotendinosos',
            'psiquismo', 'padecimiento_actual',
        ];
    }

    /**
     * @return array<int, string> columnas de hc_laboratorio
     */
    public static function laboratorio(): array
    {
        return [
            'qs_glucosa', 'qs_urea', 'qs_creatinina', 'qs_acido_urico', 'qs_colesterol', 'qs_trigliceridos',
            'qs_albumina', 'qs_bilirrubina_total', 'qs_ast', 'qs_fosfatasa_alcalina', 'qs_dhl', 'qs_hba1c',
            'bh_grupo_rh', 'bh_leucocitos', 'bh_eritrocitos', 'bh_hemoglobina', 'bh_hematocrito', 'bh_vcm',
            'bh_mch', 'bh_mchc', 'bh_plaquetas', 'bh_mpv', 'bh_neutrofilos', 'bh_linfocitos_pct',
            'bh_monocitos', 'bh_linfocitos_abs',
            'ad_opi', 'ad_coc', 'ad_thc', 'ad_bzo', 'ad_met', 'ad_amp',
            'ego_color', 'ego_aspecto', 'ego_densidad', 'ego_ph', 'ego_nitritos', 'ego_proteinas',
            'ego_glucosa', 'ego_cetonas', 'ego_bilirrubina', 'ego_urobilinogeno', 'ego_hemoglobina',
            'ego_leucocitos', 'ego_eritrocitos', 'ego_cristales',
        ];
    }

    /**
     * @return array<int, string> columnas de hc_gabinete
     */
    public static function gabinete(): array
    {
        return ['rx_torax', 'rx_lumbar', 'audiometria_resultado', 'ecg', 'espirometria', 'valoracion_oftalmologica'];
    }

    /**
     * @return array<int, int> las 9 frecuencias (en Hz) que mide la audiometria
     */
    public static function frecuenciasAudiometria(): array
    {
        return [150, 250, 500, 1000, 2000, 3000, 4000, 6000, 8000];
    }

    /**
     * @return array<int, array{orden: int, prefijo: string}> los 3 trabajos previos y su prefijo de clave frontend
     */
    public static function ordenesHistoriaLaboral(): array
    {
        return [
            ['orden' => 1, 'prefijo' => 't_actual_'],
            ['orden' => 2, 'prefijo' => 't_penultimo_'],
            ['orden' => 3, 'prefijo' => 't_antepenultimo_'],
        ];
    }
}
