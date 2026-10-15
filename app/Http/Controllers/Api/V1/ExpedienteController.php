<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccionAuditoria;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Requests\UpdateExpedienteRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Services\AuditoriaService;
use App\Services\ExpedienteBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Navegador, alta, consulta y edicion de expedientes (historias clinicas).
 * Cada creacion, consulta y edicion queda registrada en la bitacora.
 */
class ExpedienteController extends Controller
{
    public function __construct(
        private ExpedienteBuilder $builder,
        private AuditoriaService $bitacora,
    ) {}

    /**
     * Get: da el folio_anual que se asignaria si se creara un expediente
     * ahora mismo en el año dado (o en el año actual si no se especifica).
     * Es solo informativo: el folio real lo asigna FolioAnualService al insertar.
     *
     * @param  Request  $request  puede traer ?anio=
     * @return JsonResponse el siguiente folio disponible y el año consultado
     */
    public function siguienteFolio(Request $request)
    {
        $anio = (int) $request->query('anio', Carbon::now('America/Mexico_City')->year);
        $maximo = HistoriaClinica::where('anio', $anio)->max('folio_anual');

        return response()->json(['folio' => ($maximo ?? 0) + 1, 'anio' => $anio]);
    }

    /**
     * Post: crea un expediente nuevo completo y registra "Creación de Folio"
     * en la bitacora.
     *
     * @param  StoreExpedienteRequest  $request  payload plano del expediente, ya validado
     * @return JsonResponse el expediente recien creado (201)
     */
    public function store(StoreExpedienteRequest $request)
    {
        $usuario = $request->user();
        $historia = $this->builder->crear($request->validated(), $usuario);

        $motivo = $historia->tipo_examen_motivo ?? 'sin motivo capturado';
        $this->bitacora->registrar($usuario, AccionAuditoria::CreacionDeFolio, $historia, "Nueva historia clinica · {$motivo}");

        return response()->json(new ExpedienteResource($historia), 201);
    }

    /**
     * Get: lista expedientes para el navegador, con busqueda y filtros.
     *
     * @param  Request  $request  q, desde, hasta, tipo, page, page_size
     * @return JsonResponse pagina de expedientes resumidos
     */
    public function index(Request $request)
    {
        $pageSize = (int) $request->query('page_size', 12);
        $page = (int) $request->query('page', 1);

        $query = HistoriaClinica::query()->with(['paciente', 'editor', 'medico']);

        if ($q = trim((string) $request->query('q', ''))) {
            if (ctype_digit($q)) {
                // Un texto puramente numerico se interpreta como folio exacto, no como
                // subcadena de CURP/nombre/empresa (si no, un folio real queda sepultado
                // entre cientos de coincidencias de CURPs que solo comparten ese digito).
                $query->where('folio_anual', (int) $q);
            } else {
                $query->where(function ($consulta) use ($q) {
                    $consulta->whereHas('paciente', fn ($p) => $p->where('nombre_completo', 'ilike', "%{$q}%")
                        ->orWhere('curp', 'ilike', "%{$q}%"))
                        ->orWhere('empresa', 'ilike', "%{$q}%");
                });
            }
        }

        if ($desde = $request->query('desde')) {
            $query->whereDate('fecha_estudios', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->whereDate('fecha_estudios', '<=', $hasta);
        }

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo_examen_motivo', $tipo);
        }

        if ($empresa = $request->query('empresa')) {
            $query->where('empresa', 'ilike', "%{$empresa}%");
        }

        // Servicios Médicos SIEMPRE queda forzado a sus propias atenciones, sin
        // importar qué medico_id mande el cliente: la restricción real vive aquí,
        // no en el frontend. Solo un admin puede ver "todos" o elegir a cualquiera.
        $medicoId = $request->user()->rol === RolUsuario::Admin
            ? $request->query('medico_id')
            : $request->user()->id;

        if ($medicoId) {
            $query->where('medico_id', $medicoId);
        }

        if ($request->boolean('solo_examenes_visuales')) {
            $query->whereHas('exploracion', fn ($q) => $q->where('examenes_visuales_realizados', true));
        }

        $total = $query->count();

        $historias = $query->with('exploracion')
            ->orderByDesc('updated_at')
            ->forPage($page, $pageSize)
            ->get();

        return response()->json([
            'items' => $historias->map(fn (HistoriaClinica $h) => [
                // "folio" se mantiene como alias de folio_anual; "anio" es lo que el
                // frontend necesita para armar la clave de ruta "{anio}-{folio_anual}".
                'folio' => $h->folio_anual,
                'anio' => $h->anio,
                'fecha_estudios' => optional($h->fecha_estudios)->toDateString(),
                'nombre_completo' => $h->paciente?->nombre_completo,
                'curp' => $h->paciente?->curp,
                'nss' => $h->paciente?->nss,
                'empresa' => $h->empresa,
                'zona_trabajo' => $h->zona_trabajo,
                'tipo_examen_motivo' => $h->tipo_examen_motivo,
                'examenes_visuales_realizados' => (bool) $h->exploracion?->examenes_visuales_realizados,
                'medico' => $h->medico?->nombre_completo,
                'gradiente' => $h->gradiente,
                'no_apto' => (bool) $h->no_apto,
                'updated_at' => $h->updated_at?->toIso8601String(),
                'actualizado_por' => $h->editor?->nombre_completo,
            ]),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * Get: empresas distintas capturadas en algún expediente, para el filtro
     * desplegable del Reporte SABI.
     *
     * @return JsonResponse lista de empresas, alfabética
     */
    public function empresas()
    {
        return response()->json([
            'items' => HistoriaClinica::whereNotNull('empresa')->where('empresa', '!=', '')
                ->distinct()->orderBy('empresa')->pluck('empresa'),
        ]);
    }

    /**
     * Get: tarjetas de metricas del navegador de expedientes, acotadas al año
     * dado (por defecto el año actual): los años anteriores quedan pendientes
     * de respaldo/cierre y no deben mezclarse en estas cifras.
     *
     * @param  Request  $request  puede traer ?anio=
     * @return JsonResponse conteos totales, de los ultimos 7 dias, de hoy y por tipo de examen
     */
    public function estadisticas(Request $request)
    {
        $anio = (int) $request->query('anio', Carbon::now('America/Mexico_City')->year);
        $hoy = Carbon::now('America/Mexico_City')->toDateString();
        $hace7Dias = Carbon::now('America/Mexico_City')->subDays(7)->toDateString();

        $motivos = [
            'INGRESO / EVALUACIÓN INICIAL', 'RENOVACIÓN ANUAL', 'CONTROL PERIÓDICO / SEGUIMIENTO',
        ];

        $porTipo = [];
        foreach ($motivos as $motivo) {
            $porTipo[$motivo] = HistoriaClinica::where('anio', $anio)->where('tipo_examen_motivo', $motivo)->count();
        }

        return response()->json([
            'anio' => $anio,
            'total' => HistoriaClinica::where('anio', $anio)->count(),
            'ultimos_7_dias' => HistoriaClinica::where('anio', $anio)->whereDate('fecha_estudios', '>=', $hace7Dias)->count(),
            'hoy' => HistoriaClinica::where('anio', $anio)->whereDate('fecha_estudios', $hoy)->count(),
            'editados_hoy' => HistoriaClinica::where('anio', $anio)->whereDate('updated_at', $hoy)
                ->whereColumn('updated_at', '!=', 'created_at')
                ->count(),
            'por_tipo' => $porTipo,
        ]);
    }

    /**
     * Get: abre un expediente completo y registra "Consulta" en la bitacora.
     *
     * @param  string  $clave  clave de ruta "{anio}-{folio_anual}" (p. ej. "2026-6")
     * @return JsonResponse el expediente completo
     */
    public function show(Request $request, string $clave)
    {
        $historia = $this->resolverHistoria($clave);
        $historia->load(['paciente', 'domicilio', 'creador', 'editor']);

        $this->bitacora->registrar($request->user(), AccionAuditoria::Consulta, $historia, 'Apertura del expediente');

        return response()->json(new ExpedienteResource($historia));
    }

    /**
     * Put: edita un expediente existente y registra "Edición de Expediente"
     * con el detalle de que columnas cambiaron (maximo 6, luego "y N más").
     *
     * @param  UpdateExpedienteRequest  $request  payload plano del expediente, ya validado
     * @param  string  $clave  clave de ruta "{anio}-{folio_anual}" (p. ej. "2026-6")
     * @return JsonResponse el expediente actualizado
     */
    public function update(UpdateExpedienteRequest $request, string $clave)
    {
        $usuario = $request->user();
        $historia = $this->resolverHistoria($clave);
        ['historia' => $historia, 'campos' => $campos] = $this->builder->actualizar($historia, $request->validated(), $usuario);

        $this->bitacora->registrar($usuario, AccionAuditoria::EdicionDeExpediente, $historia, $this->detalleEdicion($campos));

        return response()->json(new ExpedienteResource($historia));
    }

    /**
     * Get: da los datos completos de un expediente para que el frontend
     * genere el PDF oficial (PECO o TX); el backend nunca genera el archivo,
     * solo entrega los datos y valida que quien los pide sea administrador.
     *
     * @param  Request  $request  debe traer ?plantilla=PECO|TX
     * @param  string  $clave  clave de ruta "{anio}-{folio_anual}" (p. ej. "2026-6")
     * @return JsonResponse el expediente completo mas la plantilla solicitada
     */
    public function datosParaPdf(Request $request, string $clave)
    {
        $plantilla = $request->validate([
            'plantilla' => ['required', Rule::in(['PECO', 'TX'])],
        ])['plantilla'];

        $historia = $this->resolverHistoria($clave);
        $historia->load(['paciente', 'domicilio', 'creador', 'editor']);

        $this->bitacora->registrar(
            $request->user(),
            AccionAuditoria::Consulta,
            $historia,
            "Descarga de datos para PDF ({$plantilla})",
        );

        return response()->json(array_merge(
            (new ExpedienteResource($historia))->resolve($request),
            ['plantilla' => $plantilla],
        ));
    }

    /**
     * Post: estampa la fecha de entrega de resultados con el momento actual
     * (zona Mexico), justo antes de que el frontend genere el PDF oficial.
     * Solo toca esta columna (no cuenta como una edición completa del
     * expediente, así que no se registra en la bitácora de "Edición").
     *
     * @param  string  $clave  clave de ruta "{anio}-{folio_anual}" (p. ej. "2026-6")
     * @return JsonResponse la fecha recién estampada
     */
    public function marcarEntrega(string $clave)
    {
        $historia = $this->resolverHistoria($clave);
        $fecha = Carbon::now('America/Mexico_City')->toDateString();
        $historia->update(['fecha_entrega_resultados' => $fecha]);

        return response()->json(['fecha_entrega_resultados' => $fecha]);
    }

    /**
     * Resuelve la clave de ruta "{anio}-{folio_anual}" (p. ej. "2026-6") al
     * expediente correspondiente. Reemplaza el enlace implicito por folio
     * global: ahora el folio solo es unico dentro de su año.
     *
     * @param  string  $clave  clave de ruta a resolver
     * @return HistoriaClinica el expediente encontrado
     *
     * @throws NotFoundHttpException si la clave no tiene el formato esperado o no existe
     */
    private function resolverHistoria(string $clave): HistoriaClinica
    {
        if (! preg_match('/^(\d{4})-(\d+)$/', $clave, $partes)) {
            throw new NotFoundHttpException('Clave de expediente invalida.');
        }

        return HistoriaClinica::where('anio', (int) $partes[1])
            ->where('folio_anual', (int) $partes[2])
            ->firstOrFail();
    }

    /**
     * Arma el texto de detalle de la bitacora para una edicion: hasta 6
     * etiquetas de columna separadas por coma, y "y N más" si hay mas.
     *
     * @param  array<int, string>  $campos  etiquetas de las columnas que cambiaron
     * @return string el texto de detalle para la bitacora
     */
    private function detalleEdicion(array $campos): string
    {
        if ($campos === []) {
            return 'Guardado sin cambios';
        }

        $primeros = array_slice($campos, 0, 6);
        $resto = count($campos) - count($primeros);

        return implode(', ', $primeros).($resto > 0 ? " y {$resto} más" : '');
    }
}
