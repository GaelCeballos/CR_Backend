<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Respaldo de un año que el front confirmo haber recibido completo (con el Excel
 * ya generado). Es la condicion para poder borrar ese año: el cierre compara su
 * huella con la del historial actual.
 */
class RespaldoAnio extends Model
{
    use HasUuids;

    protected $table = 'respaldos_anio';

    public $timestamps = false;

    protected $fillable = ['anio', 'modulo', 'total', 'huella', 'usuario_id', 'usuario_nombre', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'anio' => 'integer', 'total' => 'integer'];
    }
}
