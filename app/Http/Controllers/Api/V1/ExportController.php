<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HistoriaClinica;
use App\Services\ExpedienteBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Entrega los datos de la jornada para que el frontend construya el Excel
 * diario; el backend nunca genera el archivo .xlsx, solo los datos.
 */
class ExportController extends Controller
{
    public function __construct(private ExpedienteBuilder $builder) {}

    /**
     * Get: da todos los expedientes de una jornada (por defecto, hoy en
     * zona Mexico): los que se realizaron ese dia, o los que se editaron
     * ese dia aunque se hayan realizado antes.
     *
     * @param  Request  $request  ?fecha=AAAA-MM-DD, opcional
     * @return JsonResponse la fecha de la jornada y sus expedientes, ordenados por folio
     */
    public function excelData(Request $request)
    {
        $fecha = $request->query('fecha') ?: Carbon::now('America/Mexico_City')->toDateString();

        $historias = HistoriaClinica::query()
            ->where('fecha_estudios', $fecha)
            ->orWhereRaw("(updated_at AT TIME ZONE 'America/Mexico_City')::date = ?", [$fecha])
            ->with(['paciente', 'domicilio', 'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete'])
            ->orderBy('folio')
            ->get();

        return response()->json([
            'fecha' => $fecha,
            'items' => $historias->map(fn (HistoriaClinica $h) => $this->builder->instantanea($h)),
        ]);
    }
}
