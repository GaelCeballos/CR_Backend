<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Requests\UpdateExpedienteRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Services\AuditoriaService;
use App\Services\ExpedienteBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Navegador, alta, consulta y edicion de expedientes (historias clinicas).
 * Cada creacion, consulta y edicion queda registrada en la bitacora.
 */
class ExpedienteController extends Controller
{
    public function __construct(
        private ExpedienteBuilder $builder,
        private AuditoriaService $bitacora,
    ) {
    }

    /**
     * Get: da el folio que se asignaria si se creara un expediente ahora
     * mismo. Es solo informativo: el folio real lo asigna la base de datos
     * al insertar.
     *
     * @return \Illuminate\Http\JsonResponse el siguiente folio disponible
     */
    public function siguienteFolio()
    {
        return response()->json(['folio' => (int) HistoriaClinica::max('folio') + 1]);
    }

    /**
     * Post: crea un expediente nuevo completo y registra "Creación de Folio"
     * en la bitacora.
     *
     * @param  StoreExpedienteRequest  $request  payload plano del expediente, ya validado
     * @return \Illuminate\Http\JsonResponse el expediente recien creado (201)
     */
    public function store(StoreExpedienteRequest $request)
    {
        $usuario = $request->user();
        $historia = $this->builder->crear($request->validated(), $usuario);

        $motivo = $historia->tipo_examen_motivo ?? 'sin motivo capturado';
        $this->bitacora->registrar($usuario, AccionAuditoria::CreacionDeFolio, $historia, "Nueva historia clínica · {$motivo}");

        return response()->json(new ExpedienteResource($historia), 201);
    }

    /**
     * Get: lista expedientes para el navegador, con busqueda y filtros.
     *
     * @param  Request  $request  q, desde, hasta, tipo, page, page_size
     * @return \Illuminate\Http\JsonResponse pagina de expedientes resumidos
     */
    public function index(Request $request)
    {
        $pageSize = (int) $request->query('page_size', 12);
        $page = (int) $request->query('page', 1);

        $query = HistoriaClinica::query()->with(['paciente', 'editor']);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($consulta) use ($q) {
                $consulta->whereHas('paciente', fn ($p) => $p->where('nombre_completo', 'ilike', "%{$q}%")
                    ->orWhere('curp', 'ilike', "%{$q}%"))
                    ->orWhere('empresa', 'ilike', "%{$q}%");

                if (ctype_digit($q)) {
                    $consulta->orWhere('folio', (int) $q);
                }
            });
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

        $total = $query->count();

        $historias = $query->orderByDesc('updated_at')
            ->forPage($page, $pageSize)
            ->get();

        return response()->json([
            'items' => $historias->map(fn (HistoriaClinica $h) => [
                'folio' => $h->folio,
                'fecha_estudios' => optional($h->fecha_estudios)->toDateString(),
                'nombre_completo' => $h->paciente?->nombre_completo,
                'curp' => $h->paciente?->curp,
                'empresa' => $h->empresa,
                'tipo_examen_motivo' => $h->tipo_examen_motivo,
                'updated_at' => $h->updated_at?->toIso8601String(),
                'actualizado_por' => $h->editor?->nombre_completo,
            ]),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * Get: tarjetas de metricas del navegador de expedientes.
     *
     * @return \Illuminate\Http\JsonResponse conteos totales, de los ultimos 7 dias, de hoy y por tipo de examen
     */
    public function estadisticas()
    {
        $hoy = Carbon::now('America/Mexico_City')->toDateString();
        $hace7Dias = Carbon::now('America/Mexico_City')->subDays(7)->toDateString();

        $motivos = [
            'INGRESO / EVALUACIÓN INICIAL', 'RENOVACIÓN ANUAL', 'CONTROL PERIÓDICO / SEGUIMIENTO',
        ];

        $porTipo = [];
        foreach ($motivos as $motivo) {
            $porTipo[$motivo] = HistoriaClinica::where('tipo_examen_motivo', $motivo)->count();
        }

        return response()->json([
            'total' => HistoriaClinica::count(),
            'ultimos_7_dias' => HistoriaClinica::whereDate('fecha_estudios', '>=', $hace7Dias)->count(),
            'hoy' => HistoriaClinica::whereDate('fecha_estudios', $hoy)->count(),
            'editados_hoy' => HistoriaClinica::whereDate('updated_at', $hoy)
                ->whereColumn('updated_at', '!=', 'created_at')
                ->count(),
            'por_tipo' => $porTipo,
        ]);
    }

    /**
     * Get: abre un expediente completo y registra "Consulta" en la bitacora.
     *
     * @param  HistoriaClinica  $historia  expediente a consultar (enlazado por folio)
     * @return \Illuminate\Http\JsonResponse el expediente completo
     */
    public function show(Request $request, HistoriaClinica $historia)
    {
        $historia->load(['paciente', 'domicilio', 'creador', 'editor']);

        $this->bitacora->registrar($request->user(), AccionAuditoria::Consulta, $historia, 'Apertura del expediente');

        return response()->json(new ExpedienteResource($historia));
    }

    /**
     * Put: edita un expediente existente y registra "Edición de Expediente"
     * con el detalle de que columnas cambiaron (maximo 6, luego "y N más").
     *
     * @param  UpdateExpedienteRequest  $request  payload plano del expediente, ya validado
     * @param  HistoriaClinica  $historia  expediente a editar (enlazado por folio)
     * @return \Illuminate\Http\JsonResponse el expediente actualizado
     */
    public function update(UpdateExpedienteRequest $request, HistoriaClinica $historia)
    {
        $usuario = $request->user();
        ['historia' => $historia, 'campos' => $campos] = $this->builder->actualizar($historia, $request->validated(), $usuario);

        $this->bitacora->registrar($usuario, AccionAuditoria::EdicionDeExpediente, $historia, $this->detalleEdicion($campos));

        return response()->json(new ExpedienteResource($historia));
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
