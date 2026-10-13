<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Encabezado de una historia clinica (una visita/examen de un paciente). Los modulos
 * clinicos (antecedentes, exploracion, laboratorio, gabinete, historia laboral,
 * audiometria) viven en tablas aparte relacionadas por historia_id.
 */
#[Fillable([
    'paciente_id', 'domicilio_id', 'medico_id', 'folio_anual',
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

    /** El nombre de tabla no sigue la pluralizacion automatica de "HistoriaClinica". */
    protected $table = 'historias_clinicas';

    /**
     * Castea las columnas de fecha y el booleano no_apto.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_estudios' => 'date',
            'fecha_entrega_resultados' => 'date',
            'fecha_siguiente_valoracion' => 'date',
            'no_apto' => 'boolean',
        ];
    }

    /**
     * Tras insertar, vuelve a leer la fila para traer 'anio' y 'fecha_estudios',
     * que los calcula Postgres (columna generada / default) y Eloquent no los
     * recupera solo (solo recupera el id).
     */
    protected static function booted(): void
    {
        static::created(fn (HistoriaClinica $historia) => $historia->refresh());
    }

    /**
     * Paciente al que pertenece esta historia clinica.
     */
    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * Domicilio vigente del paciente al momento de este examen.
     */
    public function domicilio()
    {
        return $this->belongsTo(Domicilio::class);
    }

    /**
     * Medico que atendio esta visita.
     */
    public function medico()
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    /**
     * Usuario que creo este registro.
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Usuario que hizo la ultima edicion de este registro.
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /**
     * Antecedentes heredofamiliares, no patologicos, laborales y patologicos (1 a 1).
     */
    public function antecedentes()
    {
        return $this->hasOne(HcAntecedente::class, 'historia_id');
    }

    /**
     * Exploracion fisica completa (1 a 1).
     */
    public function exploracion()
    {
        return $this->hasOne(HcExploracion::class, 'historia_id');
    }

    /**
     * Resultados de laboratorio (1 a 1).
     */
    public function laboratorio()
    {
        return $this->hasOne(HcLaboratorio::class, 'historia_id');
    }

    /**
     * Resultados de gabinete: rayos X, ECG, espirometria, etc. (1 a 1).
     */
    public function gabinete()
    {
        return $this->hasOne(HcGabinete::class, 'historia_id');
    }

    /**
     * Los 3 trabajos previos del paciente (actual, penultimo, antepenultimo), ordenados.
     */
    public function historiaLaboral()
    {
        return $this->hasMany(HcHistoriaLaboral::class, 'historia_id')->orderBy('orden');
    }

    /**
     * Umbrales de audiometria por oido y frecuencia.
     */
    public function audiometria()
    {
        return $this->hasMany(HcAudiometria::class, 'historia_id');
    }

    /**
     * Eventos de bitacora (creacion, edicion, consulta) de esta historia.
     */
    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'historia_id');
    }
}
