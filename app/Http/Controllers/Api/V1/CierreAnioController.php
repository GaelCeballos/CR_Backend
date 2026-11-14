<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cierre y respaldo anual del historial: el admin descarga el Excel completo
 * de un año y después esos registros se borran para siempre (no se archivan:
 * el cliente decidio esto por costo de almacenamiento).
 *
 * Son dos pasos (auditoria run-1): primero se descarga el respaldo, que no
 * borra nada; el borrado solo ocurre cuando el front confirma con el total y
 * la huella que recibio al final de esa descarga, es decir, cuando el respaldo
 * llego completo y el Excel ya se genero. Antes se borraba en la misma peticion
 * en cuanto el servidor terminaba de escribir, aunque el cliente no lo recibiera.
 */
class CierreAnioController extends Controller
{
    /** Cuantas historias (con todas sus relaciones) se cargan en memoria a la vez al respaldar un año. */
    private const TAMANO_BLOQUE = 100;

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Get: da los años con historias clinicas que ya deberian respaldarse y
     * cerrarse (cualquier año anterior al actual, en zona Mexico).
     *
     * @return JsonResponse lista de años pendientes, mas reciente primero
     */
    public function pendientes()
    {
        $anios = HistoriaClinica::where('anio', '<', self::anioActual())
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio');

        return response()->json(['anios' => $anios]);
    }

    /**
     * Get: transmite el respaldo completo de un año pendiente, sin borrar nada.
     *
     * Un año puede traer miles de expedientes: se leen y se transmiten en
     * bloques de self::TAMANO_BLOQUE para que la memoria no crezca con el año.
     * La huella va al final del JSON: solo quien recibio la descarga completa
     * la tiene, y es lo que pide el paso de borrado.
     *
     * @param  Request  $request  peticion del admin
     * @param  int  $anio  año a respaldar
     * @return StreamedResponse {anio, total, items, huella}
     */
    public function respaldo(Request $request, int $anio)
    {
        $total = $this->anioCerrable($anio);

        // La descarga del historial completo de un año es una extraccion masiva
        // de datos clinicos: queda en bitacora aunque no se llegue a borrar.
        DB::transaction(fn () => $this->auditoria->registrarCierre(
            $request->user(), $anio, "Descarga del respaldo de {$anio}: {$total} expedientes.",
        ));

        return response()->stream(function () use ($anio, $total) {
            echo '{"anio":'.$anio.',"total":'.$total.',"items":[';

            $primero = true;
            $huella = hash_init('sha256');

            HistoriaClinica::where('anio', $anio)
                ->with([
                    'paciente', 'domicilio', 'creador', 'editor',
                    'antecedentes', 'historiaLaboral', 'exploracion', 'laboratorio', 'audiometria', 'gabinete',
                ])
                ->orderBy('folio_anual')
                ->chunk(self::TAMANO_BLOQUE, function ($historias) use (&$primero, $huella) {
                    foreach ($historias as $historia) {
                        echo ($primero ? '' : ',').json_encode(new ExpedienteResource($historia), JSON_THROW_ON_ERROR);
                        $primero = false;
                        hash_update($huella, self::lineaHuella($historia));
                    }

                    // Envia cada bloque al cliente de inmediato en vez de acumularlo en un buffer.
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });

            echo '],"huella":"'.hash_final($huella).'"}';
        }, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Post: borra para siempre las historias clinicas de un año ya respaldado,
     * junto con los pacientes que se queden sin ninguna visita en ningun año
     * (sus domicilios se van en cascada con ellos).
     *
     * Exige el total y la huella de la descarga: si el año cambio desde entonces
     * (se edito o se agrego un expediente), el respaldo ya no esta completo y no
     * se borra nada.
     *
     * @param  Request  $request  {total, huella} recibidos en el respaldo
     * @param  int  $anio  año a cerrar
     * @return JsonResponse {anio, total_borrado, pacientes_borrados}
     */
    public function cerrar(Request $request, int $anio)
    {
        $datos = $request->validate([
            'total' => ['required', 'integer', 'min:1'],
            'huella' => ['required', 'string', 'size:64'],
        ], [
            'total.*' => 'Falta el total del respaldo. Descarga el respaldo otra vez.',
            'huella.*' => 'Falta la huella del respaldo. Descarga el respaldo otra vez.',
        ]);

        $this->anioCerrable($anio);

        $resultado = DB::transaction(function () use ($request, $anio, $datos) {
            // Mismo candado que FolioAnualService: nada puede crear un folio de
            // ese año mientras se compara y se borra.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [(string) $anio]);

            // FOR UPDATE: una edicion simultanea espera a que termine el cierre
            // (y despues falla con 404), en vez de colarse entre la comparacion y el borrado.
            $historias = HistoriaClinica::where('anio', $anio)
                ->orderBy('folio_anual')
                ->lockForUpdate()
                ->get(['id', 'folio_anual', 'updated_at']);

            $huella = hash_init('sha256');
            foreach ($historias as $historia) {
                hash_update($huella, self::lineaHuella($historia));
            }

            if ($historias->count() !== (int) $datos['total'] || ! hash_equals(hash_final($huella), $datos['huella'])) {
                throw new ConflictException("El historial de {$anio} cambió desde la descarga. Descarga el respaldo otra vez antes de cerrar el año.");
            }

            // Cascada: se van los modulos hc_*; la bitacora de ese año queda con
            // historia_id = null pero sigue legible gracias a anio/folio_anual.
            HistoriaClinica::where('anio', $anio)->delete();
            $pacientes = Paciente::whereDoesntHave('historias')->delete();

            $this->auditoria->registrarCierre(
                $request->user(),
                $anio,
                "Cierre de {$anio}: se borraron {$historias->count()} expedientes (folios {$historias->first()->folio_anual}–{$historias->last()->folio_anual}) y {$pacientes} pacientes sin otras visitas.",
            );

            return ['total_borrado' => $historias->count(), 'pacientes_borrados' => $pacientes];
        });

        return response()->json(['anio' => $anio] + $resultado);
    }

    /**
     * Solo se cierra un año anterior al actual que todavia tenga historial: el
     * año en curso tiene expedientes vigentes y, vacio, reiniciaria sus folios
     * en 1 (claves {anio}-{folio} repetidas en la bitacora).
     *
     * @param  int  $anio  año pedido en la ruta
     * @return int expedientes del año
     *
     * @throws NotFoundApiException si el año no esta pendiente de cierre
     */
    private function anioCerrable(int $anio): int
    {
        $total = $anio < self::anioActual() ? HistoriaClinica::where('anio', $anio)->count() : 0;

        if ($total === 0) {
            throw new NotFoundApiException("No hay historial de {$anio} pendiente de cierre.");
        }

        return $total;
    }

    /** Año en curso en zona Mexico: el mismo con el que se asignan los folios. */
    private static function anioActual(): int
    {
        return Carbon::now('America/Mexico_City')->year;
    }

    /**
     * Lo que identifica a un expediente en la huella: si cambia (otro expediente,
     * o una edicion que actualiza updated_at) la huella ya no coincide.
     */
    private static function lineaHuella(HistoriaClinica $historia): string
    {
        return $historia->id.':'.$historia->updated_at?->format('Y-m-d H:i:s.u')."\n";
    }
}
