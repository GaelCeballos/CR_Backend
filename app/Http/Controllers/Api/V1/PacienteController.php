<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotFoundApiException;
use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Services\CalculoClinicoService;
use App\Support\CamposExpediente;
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
     * Get: busca un paciente recurrente por CURP o NSS exactos y devuelve los
     * datos fijos de su visita mas reciente, mas Antecedentes, Historia
     * Laboral, Exploracion Fisica y el texto de Diagnostico de esa misma
     * visita (para precargar un expediente nuevo). A proposito NO incluye
     * Laboratoriales ni Audiometria/Gabinete: esos siempre se capturan desde
     * cero en cada visita.
     *
     * @param  Request  $request  curp y/o nss, y opcionalmente excluir_clave ("{anio}-{folio_anual}")
     * @return JsonResponse paciente_id, ultimo_folio/ultimo_anio y los datos a precargar
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

        $excluirClave = $request->query('excluir_clave');
        $excluirAnio = $excluirFolio = null;

        if ($excluirClave && preg_match('/^(\d{4})-(\d+)$/', $excluirClave, $partes)) {
            [$excluirAnio, $excluirFolio] = [(int) $partes[1], (int) $partes[2]];
        }

        $ultimaHistoria = $paciente->historias()
            ->with(['domicilio', 'antecedentes', 'historiaLaboral', 'exploracion'])
            ->when($excluirAnio !== null, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('anio', '!=', $excluirAnio)
                ->orWhere('folio_anual', '!=', $excluirFolio)))
            ->latest('updated_at')
            ->first();

        $datos = [
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
            'diagnostico' => $ultimaHistoria?->diagnostico,
        ];

        foreach (CamposExpediente::antecedentes() as $columna) {
            $datos[$columna] = $ultimaHistoria?->antecedentes?->{$columna};
        }

        foreach (CamposExpediente::exploracion() as $columna) {
            $datos[$columna] = $ultimaHistoria?->exploracion?->{$columna};
        }

        $historiaLaboralPorOrden = $ultimaHistoria?->historiaLaboral->keyBy('orden') ?? collect();

        foreach (CamposExpediente::ordenesHistoriaLaboral() as ['orden' => $orden, 'prefijo' => $prefijo]) {
            $fila = $historiaLaboralPorOrden->get($orden);
            foreach (['empresa', 'puesto', 'tiempo', 'condiciones'] as $campo) {
                $datos["{$prefijo}{$campo}"] = $fila?->{$campo};
            }
        }

        return response()->json([
            'paciente_id' => $paciente->id,
            'ultimo_folio' => $ultimaHistoria?->folio_anual,
            'ultimo_anio' => $ultimaHistoria?->anio,
            'datos' => $datos,
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
