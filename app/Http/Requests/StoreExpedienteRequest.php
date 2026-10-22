<?php

namespace App\Http\Requests;

use App\Rules\NombrePersona;
use App\Support\CamposExpediente;
use App\Support\EtiquetasCampoExpediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el payload plano de un expediente (alta o edicion). Por solicitud
 * del cliente, los unicos campos obligatorios son el nombre completo, la
 * CURP o el NSS (al menos uno de los dos) y el medico dictaminador; de todos
 * los demas solo se valida el formato de los que si vienen (catalogos,
 * fechas, numeros). Tipo de sangre y los 6 resultados de antidoping tampoco
 * son obligatorios: el frontend solo avisa si quedan vacios, no bloquea el
 * guardado (ver CAMPOS_ADVERTENCIA en crm-colima).
 */
class StoreExpedienteRequest extends FormRequest
{
    /**
     * Zonas de trabajo (006) sugeridas de fabrica; debe coincidir con zonasTrabajo
     * en crm-colima. Ya no es un catalogo cerrado: se acepta cualquier zona nueva.
     */
    public const ZONAS_TRABAJO = [
        'ALZADA', 'AQUILA', 'CLUB SOCIAL', 'COLIMA', 'MINA', 'NOVA', 'PALOMAS', 'PELET', 'PRESAS', 'TECOMÁN', 'TERNIUM',
    ];

    /**
     * @return bool la autorizacion de rol la hace el middleware de la ruta
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Limpia lo que el usuario suele escribir distinto antes de validar:
     * coma decimal o "dB" en la audiometria ("25,5 dB" -> "25.5"), y la zona
     * de trabajo en mayusculas; si es una de las sugeridas escrita sin acento
     * ("tecoman", como en muchos expedientes importados del Excel) se guarda
     * como la sugerida ("TECOMÁN") para no partir la misma zona en dos.
     */
    protected function prepareForValidation(): void
    {
        $limpio = [];

        foreach (CamposExpediente::frecuenciasAudiometria() as $frecuencia) {
            foreach (['od', 'oi'] as $oido) {
                $clave = "aud{$frecuencia}_{$oido}";
                $valor = $this->input($clave);

                if (is_string($valor)) {
                    $limpio[$clave] = trim(preg_replace('/\s*db$/i', '', str_replace(',', '.', trim($valor))));
                }
            }
        }

        $zona = $this->input('zona_trabajo');

        if (is_string($zona)) {
            $limpio['zona_trabajo'] = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $zona)));
            $comparable = fn (string $texto) => strtr(mb_strtoupper(trim($texto)), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);

            foreach (static::ZONAS_TRABAJO as $oficial) {
                if ($comparable($zona) === $comparable($oficial)) {
                    $limpio['zona_trabajo'] = $oficial;
                }
            }
        }

        $this->merge($limpio);
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
            'empresa' => ['nullable', 'string'],
            'zona_trabajo' => ['nullable', 'string', 'max:255'],
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
            // Tipo de sangre y antidoping dejaron de ser obligatorios: el frontend solo avisa, no bloquea.
            'bh_grupo_rh' => ['nullable', Rule::in([
                'O POSITIVO', 'O NEGATIVO', 'A POSITIVO', 'A NEGATIVO',
                'B POSITIVO', 'B NEGATIVO', 'AB POSITIVO', 'AB NEGATIVO',
            ])],
            'ad_opi' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_coc' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_thc' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_bzo' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_met' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],
            'ad_amp' => ['nullable', Rule::in(['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])],

            'peso' => ['nullable', 'string'],
            'talla' => ['nullable', 'string'],

            'diagnostico' => ['nullable', 'string'],
            'gradiente' => ['nullable', 'string'],
            'medico_dictaminador' => ['required', 'uuid', 'exists:users,id'],
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
            'medico_dictaminador.required' => 'Selecciona el médico dictaminador / médico que atiende.',
            'medico_dictaminador.exists' => 'El médico seleccionado no existe.',
            'nombre_completo.required' => 'Escribe el nombre completo del paciente.',
            'curp.required_without' => 'Escribe la CURP o el NSS del paciente.',
            'nss.required_without' => 'Escribe el NSS o la CURP del paciente.',
        ];
    }

    /**
     * Nombres en español para los mensajes genericos (lang/es/validation.php):
     * "Zona de trabajo" en vez de "zona trabajo", sin el codigo de la columna.
     *
     * @return array<string, string> nombre visible por campo
     */
    public function attributes(): array
    {
        $nombres = [];

        foreach (array_keys($this->rules()) as $campo) {
            $nombre = preg_replace('/^\d{3} /', '', EtiquetasCampoExpediente::etiqueta($campo));
            $nombres[$campo] = str_starts_with($campo, 'aud') ? "Audiometría {$nombre}" : $nombre;
        }

        return $nombres;
    }
}
