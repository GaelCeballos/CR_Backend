<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Entrega los datos de la jornada para que el frontend construya el Excel
 * diario; el backend nunca genera el archivo .xlsx, solo los datos.
 */
class ExportController extends Controller
{
    /**
     * Get: da todos los expedientes de una jornada (por defecto, hoy en
     * zona Mexico): los que se realizaron ese dia, o los que se editaron
     * ese dia aunque se hayan realizado antes. Usa la misma forma que
     * GET /expedientes/{clave} (incluye quien creo/edito cada uno), para
     * que el Excel pueda llenar esas columnas.
     *
     * @param  Request  $request  ?fecha=AAAA-MM-DD, opcional
     * @return JsonResponse la fecha de la jornada y sus expedientes, ordenados por año y folio
     */
    public function excelData(Request $request)
    {
        $fecha = $request->query('fecha') ?: Carbon::now('America/Mexico_City')->toDateString();

        $historias = HistoriaClinica::query()
            ->where('fecha_estudios', $fecha)
            ->orWhereRaw("(updated_at AT TIME ZONE 'America/Mexico_City')::date = ?", [$fecha])
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
