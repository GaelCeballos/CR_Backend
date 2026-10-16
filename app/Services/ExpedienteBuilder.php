<?php

namespace App\Services;

use App\Models\HistoriaClinica;
use App\Models\User;
use App\Support\CamposExpediente;
use App\Support\EtiquetasCampoExpediente;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reparte el payload plano del expediente entre todas las tablas normalizadas
 * (paciente, domicilio, historia, antecedentes, historia laboral, exploracion,
 * laboratorio, audiometria, gabinete) y hace el camino de vuelta: aplana una
 * historia clinica completa a ese mismo formato plano para las respuestas y
 * para detectar que campos cambiaron en una edicion.
 */
class ExpedienteBuilder
{
    public function __construct(
        private PacienteUpsertService $pacientes,
        private CalculoClinicoService $calculos,
        private GradienteService $gradiente,
        private FolioAnualService $folios,
    ) {}

    /**
     * Crea un expediente nuevo completo: encuentra/crea al paciente, decide
     * el domicilio vigente, calcula los campos derivados y guarda todos los
     * modulos clinicos, todo dentro de una sola transaccion.
     *
     * @param  array<string, mixed>  $payload  columnas planas enviadas por el cliente
     * @param  User  $usuario  quien esta creando el expediente
     * @return HistoriaClinica el expediente recien creado, con sus relaciones cargadas
     */
    public function crear(array $payload, User $usuario): HistoriaClinica
    {
        return DB::transaction(function () use ($payload, $usuario) {
            $paciente = $this->pacientes->upsert(Arr::only($payload, CamposExpediente::paciente()));
            $domicilio = $this->pacientes->domicilioVigente($paciente, $this->datosDomicilio($payload));

            $fechaEstudios = now('America/Mexico_City')->toDateString();
            $anio = (int) substr($fechaEstudios, 0, 4);

            $historia = HistoriaClinica::create($this->datosHistoria($payload, $paciente->fecha_nacimiento, $fechaEstudios) + [
                'paciente_id' => $paciente->id,
                'domicilio_id' => $domicilio?->id,
                'folio_anual' => $this->folios->siguiente($anio),
                'creado_por' => $usuario->id,
                'actualizado_por' => $usuario->id,
            ]);

            $this->guardarModulos($historia, $payload);

            return $historia->load($this->relaciones());
        });
    }

    /**
     * Actualiza un expediente existente con un payload nuevo completo, y
     * calcula que columnas cambiaron respecto a como estaba antes.
     *
     * @param  HistoriaClinica  $historia  expediente a editar
     * @param  array<string, mixed>  $payload  columnas planas enviadas por el cliente
     * @param  User  $usuario  quien esta editando el expediente
     * @return array{historia: HistoriaClinica, campos: array<int, string>} el expediente actualizado y las etiquetas de lo que cambio
     */
    public function actualizar(HistoriaClinica $historia, array $payload, User $usuario): array
    {
        return DB::transaction(function () use ($historia, $payload, $usuario) {
            $historia->load($this->relaciones());
            $antes = $this->instantanea($historia);

            $paciente = $historia->paciente;
            $paciente->update(array_filter(
                Arr::only($payload, CamposExpediente::paciente()),
                fn ($valor) => $valor !== null,
            ));

            $domicilio = $this->pacientes->domicilioVigente($paciente, $this->datosDomicilio($payload));

            $historia->update($this->datosHistoria($payload, $paciente->fecha_nacimiento, $historia->fecha_estudios) + [
                'domicilio_id' => $domicilio?->id,
                'actualizado_por' => $usuario->id,
            ]);

            $this->guardarModulos($historia, $payload);

            $historia->refresh()->load($this->relaciones());
            $despues = $this->instantanea($historia);

            return ['historia' => $historia, 'campos' => $this->etiquetasDeCambios($antes, $despues)];
        });
    }

    /**
     * Aplana una historia clinica (con sus relaciones ya cargadas) de vuelta
     * al formato de columnas planas que usan las respuestas de la API.
     *
     * @param  HistoriaClinica  $historia  expediente con sus relaciones cargadas
     * @return array<string, mixed> todas las columnas del expediente en un solo nivel
     */
    public function instantanea(HistoriaClinica $historia): array
    {
        $plano = [
            // "folio" se mantiene como alias de folio_anual: es el nombre de columna
            // que ya usan toDbPayload/EtiquetasCampoExpediente en el resto del codigo.
            'folio' => $historia->folio_anual,
            'anio' => $historia->anio,
            'folio_anual' => $historia->folio_anual,
            'fecha_estudios' => optional($historia->fecha_estudios)->toDateString(),
            'nombre_completo' => $historia->paciente?->nombre_completo,
            'curp' => $historia->paciente?->curp,
            'nss' => $historia->paciente?->nss,
            'fecha_nacimiento' => optional($historia->paciente?->fecha_nacimiento)->toDateString(),
            'genero' => $historia->paciente?->genero,
            'originario' => $historia->paciente?->originario,
            'domicilio_calle' => $historia->domicilio?->calle,
            'domicilio_colonia' => $historia->domicilio?->colonia,
            'domicilio_municipio' => $historia->domicilio?->municipio,
            'domicilio_estado' => $historia->domicilio?->estado,
            'edad' => $historia->edad,
            'grupo_edad' => $historia->grupo_edad,
            'medico_dictaminador' => $historia->medico_id,
            'fecha_siguiente_valoracion' => $historia->no_apto ? 'NO APTO' : optional($historia->fecha_siguiente_valoracion)->toDateString(),
        ];

        foreach (CamposExpediente::historia() as $columna) {
            $plano[$columna] = $columna === 'fecha_entrega_resultados'
                ? optional($historia->{$columna})->toDateString()
                : $historia->{$columna};
        }

        foreach (CamposExpediente::antecedentes() as $columna) {
            $plano[$columna] = $historia->antecedentes?->{$columna};
        }

        foreach (CamposExpediente::ordenesHistoriaLaboral() as ['orden' => $orden, 'prefijo' => $prefijo]) {
            $fila = $historia->historiaLaboral->firstWhere('orden', $orden);

            foreach (['empresa', 'puesto', 'tiempo', 'condiciones'] as $campo) {
                $plano["{$prefijo}{$campo}"] = $fila?->{$campo};
            }
        }

        foreach (CamposExpediente::exploracion() as $columna) {
            $plano[$columna] = $historia->exploracion?->{$columna};
        }
        // Booleano: el frontend representa "marcado/sin marcar" como '1'/'' (no true/false).
        $plano['examenes_visuales_realizados'] = $historia->exploracion?->examenes_visuales_realizados ? '1' : '';
        $plano['ta_conjunta'] = $historia->exploracion?->ta_conjunta;
        $plano['imc'] = $historia->exploracion?->imc;
        $plano['estado_nutricional'] = $historia->exploracion?->estado_nutricional;

        foreach (CamposExpediente::laboratorio() as $columna) {
            $plano[$columna] = $historia->laboratorio?->{$columna};
        }

        foreach (CamposExpediente::frecuenciasAudiometria() as $frecuencia) {
            foreach (['od', 'oi'] as $oido) {
                $fila = $historia->audiometria->first(
                    fn ($u) => $u->frecuencia_hz === $frecuencia && $u->oido === mb_strtoupper($oido)
                );
                $plano["aud{$frecuencia}_{$oido}"] = $fila?->umbral_db;
            }
        }

        foreach (CamposExpediente::gabinete() as $columna) {
            $plano[$columna] = $historia->gabinete?->{$columna};
        }

        return $plano;
    }

    /**
     * @return array<int, string> las relaciones que debe traer cargadas una
     *                            historia clinica antes de aplanarla
     */
    private function relaciones(): array
    {
        return ['paciente', 'domicilio', 'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete'];
    }

    /**
     * Separa del payload los 4 campos de domicilio capturados.
     *
     * @param  array<string, mixed>  $payload  payload completo del expediente
     * @return array<string, mixed> calle, colonia, municipio y estado
     */
    private function datosDomicilio(array $payload): array
    {
        return [
            'calle' => $payload['domicilio_calle'] ?? null,
            'colonia' => $payload['domicilio_colonia'] ?? null,
            'municipio' => $payload['domicilio_municipio'] ?? null,
            'estado' => $payload['domicilio_estado'] ?? null,
        ];
    }

    /**
     * Construye el arreglo de columnas de historias_clinicas a partir del
     * payload: copia los campos simples y calcula edad, grupo de edad,
     * gradiente y el par fecha_siguiente_valoracion/no_apto.
     *
     * @param  array<string, mixed>  $payload  payload completo del expediente
     * @param  \DateTimeInterface|null  $fechaNacimiento  fecha de nacimiento del paciente
     * @param  \DateTimeInterface|string  $fechaEstudios  fecha de realizacion del examen
     * @return array<string, mixed> columnas listas para guardar en historias_clinicas
     */
    private function datosHistoria(array $payload, ?\DateTimeInterface $fechaNacimiento, \DateTimeInterface|string $fechaEstudios): array
    {
        $datos = Arr::only($payload, CamposExpediente::historia());

        if ($fechaNacimiento) {
            $edad = $this->calculos->edad($fechaNacimiento, is_string($fechaEstudios) ? new \DateTime($fechaEstudios) : $fechaEstudios);
            $datos['edad'] = $edad;
            $datos['grupo_edad'] = $edad !== null ? $this->calculos->grupoEdad($edad) : null;
        }

        if (! empty($payload['medico_dictaminador'])) {
            $datos['medico_id'] = $payload['medico_dictaminador'];
        }

        if (! empty($payload['fecha_entrega_resultados'])) {
            $datos['fecha_entrega_resultados'] = $this->calculos->parseFecha($payload['fecha_entrega_resultados'], 'fecha_entrega_resultados');
        }

        if (array_key_exists('gradiente', $payload) && $payload['gradiente'] !== null && $payload['gradiente'] !== '') {
            $datos['gradiente'] = $this->gradiente->parsear((string) $payload['gradiente']);
        }

        $valoracion = $payload['fecha_siguiente_valoracion'] ?? null;

        if ($valoracion === 'NO APTO') {
            $datos['no_apto'] = true;
            $datos['fecha_siguiente_valoracion'] = null;
        } elseif (! empty($valoracion)) {
            $datos['no_apto'] = false;
            $datos['fecha_siguiente_valoracion'] = $this->calculos->parseFecha($valoracion, 'fecha_siguiente_valoracion');
        }

        return $datos;
    }

    /**
     * Guarda los 6 modulos clinicos (antecedentes, historia laboral,
     * exploracion, laboratorio, audiometria y gabinete) de un expediente.
     *
     * @param  HistoriaClinica  $historia  expediente al que pertenecen los modulos
     * @param  array<string, mixed>  $payload  payload completo del expediente
     */
    private function guardarModulos(HistoriaClinica $historia, array $payload): void
    {
        $historia->antecedentes()->updateOrCreate([], Arr::only($payload, CamposExpediente::antecedentes()));

        $historia->historiaLaboral()->delete();
        foreach (CamposExpediente::ordenesHistoriaLaboral() as ['orden' => $orden, 'prefijo' => $prefijo]) {
            $fila = [
                'empresa' => $payload["{$prefijo}empresa"] ?? null,
                'puesto' => $payload["{$prefijo}puesto"] ?? null,
                'tiempo' => $payload["{$prefijo}tiempo"] ?? null,
                'condiciones' => $payload["{$prefijo}condiciones"] ?? null,
            ];

            if (array_filter($fila) !== []) {
                $historia->historiaLaboral()->create($fila + ['orden' => $orden]);
            }
        }

        $historia->exploracion()->updateOrCreate([], $this->datosExploracion($payload));

        $historia->laboratorio()->updateOrCreate([], Arr::only($payload, CamposExpediente::laboratorio()));

        $historia->audiometria()->delete();
        foreach (CamposExpediente::frecuenciasAudiometria() as $frecuencia) {
            foreach (['od' => 'OD', 'oi' => 'OI'] as $sufijo => $oido) {
                $umbral = $payload["aud{$frecuencia}_{$sufijo}"] ?? null;

                if ($umbral !== null && $umbral !== '') {
                    $historia->audiometria()->create([
                        'oido' => $oido,
                        'frecuencia_hz' => $frecuencia,
                        'umbral_db' => $umbral,
                    ]);
                }
            }
        }

        $historia->gabinete()->updateOrCreate([], Arr::only($payload, CamposExpediente::gabinete()));
    }

    /**
     * Da las columnas de hc_exploracion listas para guardar, normalizando la
     * talla (metros vs. centimetros) y validando el rango del peso.
     *
     * @param  array<string, mixed>  $payload  payload completo del expediente
     * @return array<string, mixed> columnas de hc_exploracion ya normalizadas
     *
     * @throws ValidationException si el peso sale del rango permitido
     */
    private function datosExploracion(array $payload): array
    {
        $datos = Arr::only($payload, CamposExpediente::exploracion());

        if (! empty($datos['talla'])) {
            $datos['talla'] = $this->calculos->normalizarTalla((string) $datos['talla']);
        }

        if (array_key_exists('peso', $datos) && $datos['peso'] !== null && $datos['peso'] !== '') {
            $peso = (float) str_replace(',', '.', (string) $datos['peso']);

            if ($peso <= 0 || $peso > 400) {
                throw ValidationException::withMessages(['peso' => 'El peso debe estar entre 0 y 400 kg.']);
            }

            $datos['peso'] = $peso;
        }

        return $datos;
    }

    /**
     * Compara dos instantaneas planas del mismo expediente y da las
     * etiquetas de los campos que cambiaron.
     *
     * @param  array<string, mixed>  $antes  instantanea antes de guardar
     * @param  array<string, mixed>  $despues  instantanea despues de guardar
     * @return array<int, string> etiquetas oficiales de las columnas que cambiaron
     */
    private function etiquetasDeCambios(array $antes, array $despues): array
    {
        // Estas columnas se derivan automaticamente de otras (peso/talla, fecha
        // de nacimiento): si cambian es solo consecuencia de un campo ya listado,
        // asi que no deben aparecer como un cambio aparte.
        $calculadas = ['ta_conjunta', 'imc', 'estado_nutricional', 'edad', 'grupo_edad', 'folio', 'anio', 'folio_anual', 'fecha_estudios'];

        $cambios = [];

        foreach ($despues as $columna => $valor) {
            if (in_array($columna, $calculadas, true)) {
                continue;
            }

            if (($antes[$columna] ?? null) != $valor) {
                $cambios[] = EtiquetasCampoExpediente::etiqueta($columna);
            }
        }

        return $cambios;
    }
}
