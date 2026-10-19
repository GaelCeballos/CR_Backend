<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el folio operativo dentro de un año (folio_anual): el historial se
 * respalda y se borra cada año, asi que el folio #1 debe poder existir en
 * 2026 y volver a existir en 2027 sin chocar con el anterior.
 */
class FolioAnualService
{
    /**
     * Da el siguiente folio_anual disponible para un año, de forma atomica.
     * Debe llamarse dentro de la misma transaccion en la que se va a insertar
     * la historia clinica: el advisory lock evita que dos altas simultaneas
     * del mismo año calculen el mismo numero.
     *
     * @param  int  $anio  año al que pertenece el folio (de fecha_estudios)
     * @return int el siguiente folio_anual, consecutivo dentro de ese año
     */
    public function siguiente(int $anio): int
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', [(string) $anio]);

        $maximo = DB::table('historias_clinicas')
            ->where('anio', $anio)
            ->max('folio_anual');

        return ($maximo ?? 0) + 1;
    }

    /**
     * Ejecuta un alta completa (historia clinica + bitacora) en su propia
     * transaccion. Si aun asi choca con otra alta simultanea (folio o paciente
     * duplicado, p. ej. dos equipos guardando al mismo tiempo), descarta el
     * intento y lo repite en vez de devolverle un error al usuario.
     *
     * @param  callable  $alta  hace el alta; debe pedir el folio con siguiente()
     * @param  int  $intentos  maximo de intentos antes de rendirse
     * @return mixed lo que devuelva $alta
     *
     * @throws UniqueConstraintViolationException si el folio sigue chocando tras todos los intentos
     */
    public function conReintento(callable $alta, int $intentos = 3): mixed
    {
        for ($intento = 1; ; $intento++) {
            try {
                // El 3 hace que Laravel tambien reintente si Postgres aborta por deadlock.
                return DB::transaction($alta, 3);
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= $intentos || ! self::esChoqueDeAlta($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @return bool true si el duplicado es del folio o del paciente (CURP/NSS),
     *              los unicos que una alta simultanea puede provocar y que se
     *              resuelven repitiendo el alta
     */
    private static function esChoqueDeAlta(UniqueConstraintViolationException $e): bool
    {
        foreach (['historias_clinicas_anio_folio_anual_unique', 'pacientes_curp_uq', 'pacientes_nss_uq'] as $restriccion) {
            if (str_contains($e->getMessage(), $restriccion)) {
                return true;
            }
        }

        return false;
    }
}
