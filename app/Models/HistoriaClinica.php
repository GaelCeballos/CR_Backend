<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'paciente_id', 'domicilio_id', 'medico_id',
    'puesto', 'empresa', 'zona_trabajo', 'tipo_examen_motivo', 'antiguedad_empresa',
    'antiguedad_puesto', 'exposicion_poe', 'area', 'tipo_examen_categoria',
    'edad', 'grupo_edad', 'telefono', 'religion', 'estado_civil',
    'diagnostico', 'gradiente',
    'fecha_entrega_resultados', 'fecha_siguiente_valoracion', 'no_apto', 'tipo_empleado',
    'creado_por', 'actualizado_por',
])]
class HistoriaClinica extends Model
{
    use HasUuids;

    protected $table = 'historias_clinicas';

    protected function casts(): array
    {
        return [
            'fecha_estudios' => 'date',
            'fecha_entrega_resultados' => 'date',
            'fecha_siguiente_valoracion' => 'date',
            'no_apto' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'folio';
    }

    protected static function booted(): void
    {
        // 'folio' (identity) y 'fecha_estudios' (default calculado) los asigna Postgres:
        // Eloquent solo recupera automáticamente la PK ('id') tras el insert.
        static::created(fn (HistoriaClinica $historia) => $historia->refresh());
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function domicilio()
    {
        return $this->belongsTo(Domicilio::class);
    }

    public function medico()
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function antecedentes()
    {
        return $this->hasOne(HcAntecedente::class, 'historia_id');
    }

    public function exploracion()
    {
        return $this->hasOne(HcExploracion::class, 'historia_id');
    }

    public function laboratorio()
    {
        return $this->hasOne(HcLaboratorio::class, 'historia_id');
    }

    public function gabinete()
    {
        return $this->hasOne(HcGabinete::class, 'historia_id');
    }

    public function historiaLaboral()
    {
        return $this->hasMany(HcHistoriaLaboral::class, 'historia_id')->orderBy('orden');
    }

    public function audiometria()
    {
        return $this->hasMany(HcAudiometria::class, 'historia_id');
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'historia_id');
    }
}
