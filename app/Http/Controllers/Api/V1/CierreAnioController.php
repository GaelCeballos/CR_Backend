<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    /** Cuantas historias (con todas sus relaciones) se cargan en memoria a la vez al respaldar un año. */
    private const TAMANO_BLOQUE = 100;

    /**
     * Post: respalda (serializa) y borra para siempre todas las historias
     * clinicas de un año, junto con los pacientes que se queden sin ninguna
     * visita en ningun año (sus domicilios se van en cascada con ellos).
     * Requiere {"confirmar": true} en el body como salvaguarda contra una
     * llamada accidental, ya que esta accion no se puede revertir.
     *
     * Un año completo puede traer miles de expedientes: en vez de cargarlos
     * todos en memoria y armar el JSON completo antes de responder, se leen
     * y se transmiten en bloques de self::TAMANO_BLOQUE, así el consumo de
     * memoria se queda igual de bajo sin importar cuántos expedientes tenga
     * el año. El borrado solo ocurre si el respaldo llegó completo al
     * cliente (si la descarga se interrumpe, los datos se quedan intactos).
     *
     * @param  Request  $request  debe traer {"confirmar": true}
     * @param  int  $anio  año a cerrar
     * @return \Symfony\Component\HttpFoundation\StreamedResponse los expedientes borrados (para que el frontend arme el Excel) y el total
     */
    public function cerrar(Request $request, int $anio)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        $total = HistoriaClinica::where('anio', $anio)->count();

        return response()->stream(function () use ($anio, $total) {
            echo '{"anio":'.$anio.',"total_borrado":'.$total.',"items":[';

            $primero = true;

            HistoriaClinica::where('anio', $anio)
                ->with([
                    'paciente', 'domicilio', 'creador', 'editor',
                    'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete',
                ])
                ->orderBy('folio_anual')
                ->chunk(self::TAMANO_BLOQUE, function ($historias) use (&$primero) {
                    foreach ($historias as $historia) {
                        if (! $primero) {
                            echo ',';
                        }
                        $primero = false;
                        echo json_encode(new ExpedienteResource($historia));
                    }

                    // Envia cada bloque al cliente de inmediato en vez de acumularlo en un buffer.
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });

            echo ']}';

            if (! connection_aborted()) {
                // Borra las historias (cascada: se van los modulos hc_* y la
                // bitacora de ese año queda con historia_id = null pero sigue
                // legible gracias a anio/folio_anual).
                HistoriaClinica::where('anio', $anio)->delete();

                // Pacientes que ya no tienen ninguna visita en ningun año: sus
                // domicilios se eliminan en cascada junto con ellos.
                Paciente::whereDoesntHave('historias')->delete();
            }
        }, 200, ['Content-Type' => 'application/json']);
    }
}
