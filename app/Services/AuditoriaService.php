<?php

namespace App\Services;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use App\Models\User;

/**
 * Unico punto que escribe en la bitacora. Quien la llama debe hacerlo dentro de la
 * misma transaccion del evento que la dispara (creacion, edicion o consulta).
 */
class AuditoriaService
{
    /**
     * Crea un registro de bitacora para una accion sobre una historia clinica.
     *
     * @param  User  $usuario  quien realizo la accion
     * @param  AccionAuditoria  $accion  tipo de evento (creacion, edicion o consulta)
     * @param  HistoriaClinica  $historia  expediente sobre el que se actuo
     * @param  string  $detalle  descripcion corta de lo que cambio o se consulto
     * @return Auditoria el registro de bitacora recien creado
     */
    public function registrar(User $usuario, AccionAuditoria $accion, HistoriaClinica $historia, string $detalle): Auditoria
    {
        return Auditoria::create([
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->nombre_completo,
            'accion' => $accion,
            'historia_id' => $historia->id,
            'anio' => $historia->anio,
            'folio_anual' => $historia->folio_anual,
            'paciente' => $historia->paciente?->nombre_completo,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Registra un evento de un año completo (respaldo o cierre). No pertenece a
     * un folio: folio_anual va en 0 y sin paciente, para que el evento no se
     * confunda con el de un expediente.
     *
     * @param  User  $usuario  admin que realizo la accion
     * @param  AccionAuditoria  $accion  RespaldoDeAnio o CierreDeAnio
     * @param  int  $anio  año respaldado o cerrado
     * @param  string  $detalle  que se descargo o se borro
     * @return Auditoria el registro de bitacora recien creado
     */
    public function registrarAnio(User $usuario, AccionAuditoria $accion, int $anio, string $detalle): Auditoria
    {
        return Auditoria::create([
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->nombre_completo,
            'accion' => $accion,
            'historia_id' => null,
            'anio' => $anio,
            'folio_anual' => 0,
            'paciente' => null,
            'detalle' => $detalle,
        ]);
    }
}
