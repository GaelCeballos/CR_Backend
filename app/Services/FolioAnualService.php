<?php

namespace App\Services;

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
}
