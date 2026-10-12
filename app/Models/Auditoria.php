<?php

namespace App\Models;

use App\Enums\AccionAuditoria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['usuario_id', 'usuario_nombre', 'accion', 'historia_id', 'folio', 'paciente', 'detalle'])]
class Auditoria extends Model
{
    use HasUuids;

    protected $table = 'auditoria';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'accion' => AccionAuditoria::class,
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function historia()
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_id');
    }
}
