<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Entrega los datos de la jornada para que el frontend construya el Excel
 * diario; el backend nunca genera el archivo .xlsx, solo los datos.
 */
class ExportController extends Controller
{
    /**
     * Get: da los expedientes de una jornada (por defecto, hoy en zona Mexico)
     * para el Excel diario. Por defecto solo los creados ese dia (peticion del
     * cliente 2026-11-14: antes salian tambien los editados y el reporte del dia
     * traia folios de otros dias); con editados=1 agrega los que se editaron ese
     * dia. Usa la misma forma que GET /expedientes/{clave} (incluye quien
     * creo/edito cada uno), para que el Excel pueda llenar esas columnas.
     *
     * "Editado" sale de la bitacora (Edicion de Expediente) y no de updated_at:
     * marcar la entrega de resultados al generar el PDF tambien mueve updated_at
     * y no es una edicion.
     *
     * @param  Request  $request  ?fecha=AAAA-MM-DD&creados=1&editados=0, todos opcionales
     * @return JsonResponse la fecha de la jornada y sus expedientes, ordenados por año y folio
     */
    public function excelData(Request $request)
    {
        $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'creados' => ['nullable', 'boolean'],
            'editados' => ['nullable', 'boolean'],
        ], [
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
        ]);

        $fecha = $request->query('fecha') ?: Carbon::now('America/Mexico_City')->toDateString();
        $creados = $request->boolean('creados', true);
        $editados = $request->boolean('editados', false);

        if (! $creados && ! $editados) {
            throw ValidationException::withMessages(['creados' => 'Elige al menos creados o editados.']);
        }

        // Rango del dia en hora de Mexico, con su zona horaria escrita (P): sin ella,
        // Postgres lo lee como UTC y el dia queda corrido 6 horas. Asi la columna
        // se compara tal cual y usa su indice.
        $dia = Carbon::parse($fecha, 'America/Mexico_City');
        $inicio = $dia->copy()->startOfDay()->format('Y-m-d H:i:s.uP');
        $fin = $dia->copy()->endOfDay()->format('Y-m-d H:i:s.uP');

        $historias = HistoriaClinica::query()
            ->where(function ($consulta) use ($creados, $editados, $fecha, $inicio, $fin) {
                if ($creados) {
                    $consulta->orWhere('fecha_estudios', $fecha);
                }
                if ($editados) {
                    $consulta->orWhereIn('id', Auditoria::select('historia_id')
                        ->where('accion', AccionAuditoria::EdicionDeExpediente)
                        ->whereNotNull('historia_id')
                        ->whereBetween('fecha', [$inicio, $fin]));
                }
            })
            ->with([
                'paciente', 'domicilio', 'creador', 'editor',
                'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete',
            ])
            ->orderBy('anio')
            ->orderBy('folio_anual')
            ->get();

        return response()->json([
            'fecha' => $fecha,
            'items' => ExpedienteResource::collection($historias),
        ]);
    }
}
