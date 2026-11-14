<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccionAuditoria;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExpedienteResource;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\RespaldoAnio;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cierre y respaldo anual del historial: el admin descarga el Excel completo de
 * un año y, cuando decide, esos registros se borran para siempre (no se
 * archivan: el cliente decidio esto por costo de almacenamiento).
 *
 * Respaldar y borrar son acciones separadas (auditoria run-1 y decision del
 * dueño 2026-11-14): el respaldo se puede descargar las veces que se quiera; el
 * front confirma cuando el Excel ya se genero y el servidor guarda ese respaldo
 * con su huella. El borrado solo procede si el ultimo respaldo sigue vigente
 * (nadie cambio el año despues), aunque se haga otro dia.
 */
class CierreAnioController extends Controller
{
    /** Cuantas historias (con todas sus relaciones) se cargan en memoria a la vez al respaldar un año. */
    private const TAMANO_BLOQUE = 100;

    /** Hoy solo se cierra Empresas; Publico en General tendra su propio cierre. */
    private const MODULO = 'empresas';

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Get: años anteriores al actual que todavia tienen historial, con su total
     * y su ultimo respaldo (y si sigue vigente para poder borrar).
     *
     * @return JsonResponse {anios: [{anio, total, respaldo: {fecha, usuario_nombre, total, vigente} | null}]}
     */
    public function pendientes()
    {
        $anios = HistoriaClinica::where('anio', '<', self::anioActual())
            ->selectRaw('anio, count(*) as total')
            ->groupBy('anio')
            ->orderByDesc('anio')
            ->get();

        return response()->json(['anios' => $anios->map(function ($fila) {
            $respaldo = self::ultimoRespaldo($fila->anio);

            return [
                'anio' => $fila->anio,
                'total' => $fila->total,
                'respaldo' => $respaldo ? [
                    'fecha' => $respaldo->fecha->toIso8601String(),
                    'usuario_nombre' => $respaldo->usuario_nombre,
                    'total' => $respaldo->total,
                    'vigente' => hash_equals($respaldo->huella, self::huellaActual($fila->anio)),
                ] : null,
            ];
        })]);
    }

    /**
     * Get: transmite el respaldo completo de un año pendiente, sin borrar nada.
     *
     * Un año puede traer miles de expedientes: se leen y se transmiten en
     * bloques de self::TAMANO_BLOQUE para que la memoria no crezca con el año.
     * La huella va al final del JSON: solo quien recibio la descarga completa la
     * tiene, y es la que se manda al confirmar el respaldo.
     *
     * @param  Request  $request  peticion del admin
     * @param  int  $anio  año a respaldar
     * @return StreamedResponse {anio, total, items, huella}
     */
    public function respaldo(Request $request, int $anio)
    {
        $total = $this->anioCerrable($anio);

        // La descarga del historial completo de un año es una extraccion masiva
        // de datos clinicos: queda en bitacora aunque nunca se confirme.
        DB::transaction(fn () => $this->auditoria->registrarAnio(
            $request->user(), AccionAuditoria::RespaldoDeAnio, $anio, "Descarga del respaldo de {$anio}: {$total} expedientes.",
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
     * Post: el front avisa que el respaldo llego completo y el Excel ya se
     * genero. Se guarda solo si la huella coincide con el historial actual: si
     * alguien edito el año durante la descarga, ese Excel ya no esta al dia.
     *
     * @param  Request  $request  {total, huella} recibidos al final del respaldo
     * @param  int  $anio  año respaldado
     * @return JsonResponse {anio, total, fecha}
     */
    public function confirmarRespaldo(Request $request, int $anio)
    {
        $datos = $request->validate([
            'total' => ['required', 'integer', 'min:1'],
            'huella' => ['required', 'string', 'size:64'],
        ], [
            'total.*' => 'Falta el total del respaldo. Descarga el respaldo otra vez.',
            'huella.*' => 'Falta la huella del respaldo. Descarga el respaldo otra vez.',
        ]);

        $total = $this->anioCerrable($anio);

        if ($total !== (int) $datos['total'] || ! hash_equals(self::huellaActual($anio), $datos['huella'])) {
            throw new ConflictException("El historial de {$anio} cambió durante la descarga. Descarga el respaldo otra vez.");
        }

        $respaldo = RespaldoAnio::create([
            'anio' => $anio,
            'modulo' => self::MODULO,
            'total' => $total,
            'huella' => $datos['huella'],
            'usuario_id' => $request->user()->id,
            'usuario_nombre' => $request->user()->nombre_completo,
            // Hora de PHP y no now() de Postgres: now() es la del inicio de la
            // transaccion, y "el ultimo respaldo" se decide ordenando por fecha.
            'fecha' => now(),
        ]);

        return response()->json(['anio' => $anio, 'total' => $total, 'fecha' => $respaldo->fecha->toIso8601String()]);
    }

    /**
     * Post: borra para siempre las historias clinicas de un año ya respaldado,
     * junto con los pacientes que se queden sin ninguna visita en ningun año
     * (sus domicilios se van en cascada con ellos).
     *
     * Exige escribir "CERRAR {anio}" y un respaldo vigente: si el año cambio
     * despues del ultimo respaldo (se edito un expediente), ese Excel ya no
     * tiene todo y no se borra nada.
     *
     * @param  Request  $request  {confirmacion: "CERRAR 2025"}
     * @param  int  $anio  año a cerrar
     * @return JsonResponse {anio, total_borrado, pacientes_borrados}
     */
    public function cerrar(Request $request, int $anio)
    {
        $request->validate(['confirmacion' => ['required', 'string']]);

        if (mb_strtoupper(trim($request->input('confirmacion'))) !== "CERRAR {$anio}") {
            throw ValidationException::withMessages(['confirmacion' => "Escribe CERRAR {$anio} para confirmar."]);
        }

        $this->anioCerrable($anio);

        $resultado = DB::transaction(function () use ($request, $anio) {
            // Mismo candado que FolioAnualService: nada puede crear un folio de
            // ese año mientras se compara y se borra.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [(string) $anio]);

            // FOR UPDATE: una edicion simultanea espera a que termine el cierre
            // (y despues falla con 404), en vez de colarse entre la comparacion y el borrado.
            $historias = HistoriaClinica::where('anio', $anio)
                ->orderBy('folio_anual')
                ->lockForUpdate()
                ->get(['id', 'folio_anual', 'updated_at']);

            $respaldo = self::ultimoRespaldo($anio);

            if (! $respaldo) {
                throw new ConflictException("Primero descarga el respaldo de {$anio}: solo se borra un año ya respaldado.");
            }

            if (! hash_equals($respaldo->huella, self::huella($historias))) {
                throw new ConflictException("El historial de {$anio} cambió después del último respaldo. Descarga el respaldo otra vez antes de borrar.");
            }

            // Cascada: se van los modulos hc_*; la bitacora de ese año queda con
            // historia_id = null pero sigue legible gracias a anio/folio_anual.
            HistoriaClinica::where('anio', $anio)->delete();
            $pacientes = Paciente::whereDoesntHave('historias')->delete();

            $fechaRespaldo = $respaldo->fecha->timezone('America/Mexico_City')->format('d/m/Y H:i');
            $this->auditoria->registrarAnio(
                $request->user(),
                AccionAuditoria::CierreDeAnio,
                $anio,
                "Se borraron {$historias->count()} expedientes de {$anio} (folios {$historias->first()->folio_anual}–{$historias->last()->folio_anual}) "
                ."y {$pacientes} pacientes sin otras visitas. Respaldo del {$fechaRespaldo} por {$respaldo->usuario_nombre}.",
            );

            return ['total_borrado' => $historias->count(), 'pacientes_borrados' => $pacientes];
        });

        return response()->json(['anio' => $anio] + $resultado);
    }

    /**
     * Solo se respalda o se cierra un año anterior al actual que todavia tenga
     * historial: el año en curso tiene expedientes vigentes y, vacio, reiniciaria
     * sus folios en 1 (claves {anio}-{folio} repetidas en la bitacora).
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

    /** Ultimo respaldo confirmado de un año: el que decide si se puede borrar. */
    private static function ultimoRespaldo(int $anio): ?RespaldoAnio
    {
        return RespaldoAnio::where('anio', $anio)->where('modulo', self::MODULO)->orderByDesc('fecha')->first();
    }

    /** Huella del historial actual de un año (sin bloquear: solo para comparar). */
    private static function huellaActual(int $anio): string
    {
        return self::huella(HistoriaClinica::where('anio', $anio)->orderBy('folio_anual')->get(['id', 'updated_at']));
    }

    /**
     * sha256 de "id:updated_at" de cada expediente, en orden de folio. Debe
     * recorrerlos en el mismo orden que respaldo().
     *
     * @param  iterable<HistoriaClinica>  $historias
     */
    private static function huella(iterable $historias): string
    {
        $huella = hash_init('sha256');
        foreach ($historias as $historia) {
            hash_update($huella, self::lineaHuella($historia));
        }

        return hash_final($huella);
    }

    /**
     * Lo que identifica a un expediente en la huella: si cambia (otro expediente,
     * o una edicion, que actualiza updated_at) la huella ya no coincide.
     */
    private static function lineaHuella(HistoriaClinica $historia): string
    {
        return $historia->id.':'.$historia->updated_at?->format('Y-m-d H:i:s.u')."\n";
    }
}
