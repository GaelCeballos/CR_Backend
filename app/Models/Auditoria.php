<?php

namespace App\Models;

use App\Enums\AccionAuditoria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Un evento de bitacora: quien hizo que, sobre que expediente y cuando. Solo el
 * servidor escribe en esta tabla (ver App\Services\AuditoriaService).
 */
#[Fillable(['usuario_id', 'usuario_nombre', 'accion', 'historia_id', 'anio', 'folio_anual', 'paciente', 'detalle'])]
class Auditoria extends Model
{
    use HasUuids;

    /** El nombre de tabla es singular, no sigue la pluralizacion automatica del modelo. */
    protected $table = 'auditoria';

    /** Usa su propia columna "fecha" en vez de created_at/updated_at. */
    public $timestamps = false;

    /**
     * Castea fecha a un objeto de fecha/hora y accion a su enum.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'accion' => AccionAuditoria::class,
        ];
    }

    /**
     * Usuario que genero este evento.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Historia clinica involucrada en este evento (si aplica).
     */
    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
