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
            'folio' => $historia->folio,
            'paciente' => $historia->paciente?->nombre_completo,
            'detalle' => $detalle,
        ]);
    }
}
