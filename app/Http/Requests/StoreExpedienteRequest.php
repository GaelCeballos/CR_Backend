<?php

namespace App\Http\Requests;

use App\Rules\NombrePersona;
use App\Support\CamposExpediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el payload plano de un expediente (alta o edicion). La mayoria de
 * los campos clinicos son opcionales (solo se valida el formato de los que
 * si vienen); nombre, CURP/NSS, empresa, tipo de sangre y los 6 resultados
 * de antidoping son obligatorios.
 */
class StoreExpedienteRequest extends FormRequest
{
    /**
     * @return bool la autorizacion de rol la hace el middleware de la ruta
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Arma las reglas: texto libre "nullable|string" para la mayoria de las
     * columnas, con overrides para los catalogos cerrados y los campos con
     * un formato especial (fechas, numeros, identificadores).
     *
     * @return array<string, array<mixed>> reglas de validacion por campo
     */
    public function rules(): array
    {
        $reglas = [];

        foreach ([
            CamposExpediente::antecedentes(),
            CamposExpediente::exploracion(),
            CamposExpediente::laboratorio(),
            CamposExpediente::gabinete(),
        ] as $grupo) {
            foreach ($grupo as $columna) {
                $reglas[$columna] = ['nullable', 'string'];
            }
        }

        foreach (CamposExpediente::ordenesHistoriaLaboral() as ['prefijo' => $prefijo]) {
            foreach (['empresa', 'puesto', 'tiempo', 'condiciones'] as $campo) {
                $reglas["{$prefijo}{$campo}"] = ['nullable', 'string'];
            }
        }

        foreach (CamposExpediente::frecuenciasAudiometria() as $frecuencia) {
            foreach (['od', 'oi'] as $oido) {
                $reglas["aud{$frecuencia}_{$oido}"] = ['nullable', 'numeric', 'between:-10,120'];
            }
        }

        return array_merge($reglas, [
            'nombre_completo' => ['required', 'string', new NombrePersona],
            // CURP y NSS son opcionales por separado, pero no pueden faltar los dos a la vez.
            'curp' => ['nullable', 'required_without:nss', 'string'],
            'nss' => ['nullable', 'required_without:curp', 'string'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', Rule::in(['MASCULINO', 'FEMENINO'])],
            'originario' => ['nullable', 'string'],

            'domicilio_calle' => ['nullable', 'string'],
            'domicilio_colonia' => ['nullable', 'string'],
            'domicilio_municipio' => ['nullable', 'string'],
            'domicilio_estado' => ['nullable', 'string'],

            'puesto' => ['nullable', 'string'],
            'empresa' => ['required', 'string'],
            'zona_trabajo' => ['nullable', Rule::in([
                'ALZADA', 'AQUILA', 'CLUB SOCIAL', 'COLIMA', 'MINA', 'NOVA', 'PALOMAS', 'PELET', 'PRESAS', 'TECOMÁN', 'TERNIUM',
            ])],
            'tipo_examen_motivo' => ['nullable', Rule::in([
                'INGRESO / EVALUACIÓN INICIAL', 'RENOVACIÓN ANUAL', 'CONTROL PERIÓDICO / SEGUIMIENTO',
            ])],
            'antiguedad_empresa' => ['nullable', 'string'],
            'antiguedad_puesto' => ['nullable', 'string'],
            'exposicion_poe' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'tipo_examen_categoria' => ['nullable', Rule::in([
                'PERSONAL GENERAL', 'OPERADORES', 'CAT B', 'CAT C', 'VISITA TEC',
            ])],
            'telefono' => ['nullable', 'string'],
            'religion' => ['nullable', Rule::in(['Católica', 'Cristiana', 'Ninguna', 'Atea', 'Negado', 'Otra'])],
            'estado_civil' => ['nullable', Rule::in([
                'SOLTERO', 'CASADO', 'UNION LIBRE', 'DIVORCIADO', 'SEPARADO', 'VIUDO',
            ])],
            'escolaridad' => ['nullable', Rule::in([
                'Sin Escolaridad', 'Primaria Incompleta', 'Primaria Terminada',
                'Secundaria Incompleta', 'Secundaria Terminada', 'Preparatoria Incompleta',
                'Preparatoria Terminada', 'Licenciatura Incompleta', 'Licenciatura Terminada',
                'Maestría / Postgrado',
            ])],
            'lentes_seguridad' => ['nullable', Rule::in([
                'PX NO NECESITA LENTES GRADUADOS DE SEGURIDAD', 'PX REQUIERE LENTES GRADUADOS DE SEGURIDAD',
            ])],
            'examenes_visuales_realizados' => ['nullable', 'boolean'],
            'bh_grupo_rh' => ['required', Rule::in([
                'O POSITIVO', 'O NEGATIVO', 'A POSITIVO', 'A NEGATIVO',
                'B POSITIVO', 'B NEGATIVO', 'AB POSITIVO', 'AB NEGATIVO',
            ])],
            'ad_opi' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_coc' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_thc' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_bzo' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_met' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_amp' => ['required', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],

            'peso' => ['nullable', 'string'],
            'talla' => ['nullable', 'string'],

            'diagnostico' => ['nullable', 'string'],
            'gradiente' => ['nullable', 'string'],
            'medico_dictaminador' => ['nullable', 'uuid', 'exists:users,id'],
            'fecha_entrega_resultados' => ['nullable', 'string'],
            'fecha_siguiente_valoracion' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, string> mensajes personalizados por regla fallida
     */
    public function messages(): array
    {
        return [
            'medico_dictaminador.exists' => 'El médico seleccionado no existe.',
            'nombre_completo.required' => 'Escribe el nombre completo del paciente.',
            'curp.required_without' => 'Escribe la CURP o el NSS del paciente.',
            'nss.required_without' => 'Escribe el NSS o la CURP del paciente.',
            'empresa.required' => 'La empresa es obligatoria.',
            'bh_grupo_rh.required' => 'Selecciona el tipo de sangre.',
            'ad_opi.required' => 'Selecciona un resultado de Opiáceos (OPI).',
            'ad_coc.required' => 'Selecciona un resultado de Cocaína (COC).',
            'ad_thc.required' => 'Selecciona un resultado de Cannabinoides (THC).',
            'ad_bzo.required' => 'Selecciona un resultado de Benzodiacepinas (BZO).',
            'ad_met.required' => 'Selecciona un resultado de Metanfetaminas (MET).',
            'ad_amp.required' => 'Selecciona un resultado de Anfetaminas (AMP).',
        ];
    }
}
