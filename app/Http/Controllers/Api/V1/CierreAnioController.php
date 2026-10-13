<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cierre y respaldo anual del historial: el admin descarga el Excel completo
 * de un año y, en ese mismo momento, esos registros se borran para siempre
 * (no se archivan: el cliente decidio esto por costo de almacenamiento).
 */
class CierreAnioController extends Controller
{
    /**
     * Get: da los años con historias clinicas que ya deberian respaldarse y
     * cerrarse (cualquier año anterior al actual, en zona Mexico).
     *
     * @return JsonResponse lista de años pendientes, mas reciente primero
     */
    public function pendientes()
    {
        $anioActual = Carbon::now('America/Mexico_City')->year;

        $anios = HistoriaClinica::where('anio', '<', $anioActual)
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio');

        return response()->json(['anios' => $anios]);
    }

    /**
     * Post: respalda (serializa) y borra para siempre todas las historias
     * clinicas de un año, junto con los pacientes que se queden sin ninguna
     * visita en ningun año (sus domicilios se van en cascada con ellos).
     * Requiere {"confirmar": true} en el body como salvaguarda contra una
     * llamada accidental, ya que esta accion no se puede revertir.
     *
     * @param  Request  $request  debe traer {"confirmar": true}
     * @param  int  $anio  año a cerrar
     * @return JsonResponse los expedientes borrados (para que el frontend arme el Excel) y el total
     */
    public function cerrar(Request $request, int $anio)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        return DB::transaction(function () use ($anio) {
            $historias = HistoriaClinica::where('anio', $anio)
                ->with([
                    'paciente', 'domicilio', 'creador', 'editor',
                    'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete',
                ])
                ->orderBy('folio_anual')
                ->get();

            $total = $historias->count();

            // Borra las historias (cascada: se van los modulos hc_* y la
            // bitacora de ese año queda con historia_id = null pero sigue
            // legible gracias a anio/folio_anual).
            HistoriaClinica::where('anio', $anio)->delete();

            // Pacientes que ya no tienen ninguna visita en ningun año: sus
            // domicilios se eliminan en cascada junto con ellos.
            Paciente::whereDoesntHave('historias')->delete();

            return response()->json([
                'anio' => $anio,
                'total_borrado' => $total,
                'items' => ExpedienteResource::collection($historias),
            ]);
        });
    }
}
