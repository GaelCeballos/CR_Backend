<?php

namespace App\Services;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use App\Models\User;

/**
 * Unico punto que escribe en la bitacora (spec §3.5, §4.6). Quien la llama debe
 * hacerlo dentro de la misma transaccion del evento que la dispara.
 */
class AuditoriaService
{
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
