<?php

namespace App\Http\Resources;

use App\Services\ExpedienteBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da la forma publica de un expediente completo: folio, fecha de los
 * estudios, todas las columnas aplanadas en "values", y quien lo creo.
 */
class ExpedienteResource extends JsonResource
{
    /**
     * @param  Request  $request  peticion HTTP actual
     * @return array<string, mixed> expediente completo listo para el cliente
     */
    public function toArray(Request $request): array
    {
        $builder = app(ExpedienteBuilder::class);

        return [
            'folio' => $this->folio,
            'fecha_estudios' => optional($this->fecha_estudios)->toDateString(),
            'values' => $builder->instantanea($this->resource),
            'creado_por' => [
                'id' => $this->creado_por,
                'nombre' => $this->creador?->nombre_completo,
            ],
            'actualizado_por' => [
                'id' => $this->actualizado_por,
                'nombre' => $this->editor?->nombre_completo,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
