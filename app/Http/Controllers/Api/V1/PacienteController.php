<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotFoundApiException;
use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Services\CalculoClinicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador de pacientes del encabezado y precarga de datos para pacientes
 * recurrentes al crear un expediente nuevo.
 */
class PacienteController extends Controller
{
    public function __construct(private CalculoClinicoService $calculos) {}

    /**
     * Get: busca pacientes por CURP, NSS o nombre (sin importar acentos),
     * para el buscador del encabezado.
     *
     * @param  Request  $request  parametro "q", al menos 2 caracteres
     * @return JsonResponse hasta 8 coincidencias
     */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $qNormalizado = $this->sinAcentos(mb_strtoupper($q));

        $pacientes = Paciente::where(function ($consulta) use ($q, $qNormalizado) {
            $consulta->where('curp', 'ilike', "{$q}%")
                ->orWhere('nss', 'ilike', "{$q}%")
                ->orWhereRaw(
                    "translate(upper(nombre_completo), 'ÁÉÍÓÚÑÜ', 'AEIOUNU') ilike ?",
                    ["%{$qNormalizado}%"],
                );
        })->limit(8)->get(['id', 'nombre_completo', 'curp', 'nss']);

        return response()->json(['items' => $pacientes]);
    }

    /**
     * Get: busca un paciente recurrente por CURP o NSS exactos y devuelve
     * los datos fijos de su visita mas reciente, para precargar un expediente
     * nuevo.
     *
     * @param  Request  $request  curp y/o nss, y opcionalmente excluir_folio
     * @return JsonResponse paciente_id, ultimo_folio y los datos fijos
     */
    public function lookup(Request $request)
    {
        $curp = $this->calculos->claveCurp($request->query('curp'));
        $nss = $this->calculos->claveNss($request->query('nss'));

        $paciente = null;

        if ($curp) {
            $paciente = Paciente::where('curp', $curp)->first();
        }

        if (! $paciente && $nss) {
            $paciente = Paciente::where('nss', $nss)->first();
        }

        if (! $paciente) {
            throw new NotFoundApiException('No se encontró ningún paciente con ese identificador.');
        }

        $ultimaHistoria = $paciente->historias()
            ->with('domicilio')
            ->when($request->query('excluir_folio'), fn ($q, $folio) => $q->where('folio', '!=', $folio))
            ->latest('updated_at')
            ->first();

        return response()->json([
            'paciente_id' => $paciente->id,
            'ultimo_folio' => $ultimaHistoria?->folio,
            'datos' => [
                'nombre_completo' => $paciente->nombre_completo,
                'fecha_nacimiento' => optional($paciente->fecha_nacimiento)->toDateString(),
                'genero' => $paciente->genero,
                'curp' => $paciente->curp,
                'nss' => $paciente->nss,
                'domicilio_calle' => $ultimaHistoria?->domicilio?->calle,
                'domicilio_colonia' => $ultimaHistoria?->domicilio?->colonia,
                'domicilio_municipio' => $ultimaHistoria?->domicilio?->municipio,
                'domicilio_estado' => $ultimaHistoria?->domicilio?->estado,
                'telefono' => $ultimaHistoria?->telefono,
                'empresa' => $ultimaHistoria?->empresa,
                'puesto' => $ultimaHistoria?->puesto,
            ],
        ]);
    }

    /**
     * Quita los acentos y la diéresis mas comunes del español para
     * comparaciones de busqueda que no distingan acentos.
     *
     * @param  string  $texto  texto en mayusculas a limpiar
     * @return string el mismo texto sin acentos
     */
    private function sinAcentos(string $texto): string
    {
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'U'],
            $texto,
        );
    }
}
