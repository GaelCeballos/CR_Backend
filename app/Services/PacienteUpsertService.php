<?php

namespace App\Services;

use App\Models\Domicilio;
use App\Models\Paciente;
use Illuminate\Validation\ValidationException;

/**
 * Encuentra o crea al paciente de una historia clinica nueva, y decide si el
 * domicilio capturado es una instantanea nueva o sigue siendo el mismo.
 */
class PacienteUpsertService
{
    public function __construct(private CalculoClinicoService $calculos) {}

    /**
     * Busca un paciente existente por CURP o NSS normalizados; si no existe,
     * crea uno nuevo. Si existe, actualiza sus datos fijos con los que llegaron.
     *
     * @param  array<string, mixed>  $datos  curp, nss, nombre_completo, fecha_nacimiento, genero, originario
     * @return Paciente el paciente encontrado o recien creado
     */
    public function upsert(array $datos): Paciente
    {
        $curp = $this->calculos->claveCurp($datos['curp'] ?? null);
        $nss = $this->calculos->claveNss($datos['nss'] ?? null);

        if ($curp !== null && strlen($curp) !== 18) {
            throw ValidationException::withMessages(['curp' => 'La CURP debe tener 18 caracteres.']);
        }

        if ($nss !== null && strlen($nss) !== 11) {
            throw ValidationException::withMessages(['nss' => 'El NSS debe tener 11 dígitos.']);
        }

        $paciente = $this->buscarExistente($curp, $nss);

        $atributos = array_filter([
            'nombre_completo' => $datos['nombre_completo'] ?? null,
            'curp' => $curp,
            'nss' => $nss,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'genero' => $datos['genero'] ?? null,
            'originario' => $datos['originario'] ?? null,
        ], fn ($valor) => $valor !== null);

        if ($paciente) {
            $paciente->update($atributos);

            return $paciente;
        }

        return Paciente::create($atributos);
    }

    /**
     * Da el domicilio vigente de un paciente para una nueva historia clinica:
     * si lo capturado es igual al domicilio mas reciente, reutiliza esa fila;
     * si cambio algo, inserta una instantanea nueva.
     *
     * @param  Paciente  $paciente  paciente al que pertenece el domicilio
     * @param  array<string, mixed>  $datos  calle, colonia, municipio, estado
     * @return Domicilio|null el domicilio a usar, o null si no se capturo ninguno
     */
    public function domicilioVigente(Paciente $paciente, array $datos): ?Domicilio
    {
        $nuevo = [
            'calle' => $datos['calle'] ?? null,
            'colonia' => $datos['colonia'] ?? null,
            'municipio' => $datos['municipio'] ?? null,
            'estado' => $datos['estado'] ?? null,
        ];

        if (array_filter($nuevo) === []) {
            return $paciente->domicilios()->latest('created_at')->first();
        }

        $ultimo = $paciente->domicilios()->latest('created_at')->first();

        if ($ultimo && $this->mismoDomicilio($ultimo, $nuevo)) {
            return $ultimo;
        }

        return $paciente->domicilios()->create($nuevo);
    }

    /**
     * Busca un paciente por CURP o por NSS, cualquiera de los dos que venga.
     *
     * @param  string|null  $curp  CURP ya normalizado
     * @param  string|null  $nss  NSS ya normalizado
     * @return Paciente|null el paciente encontrado, o null si no hay coincidencia
     */
    private function buscarExistente(?string $curp, ?string $nss): ?Paciente
    {
        if ($curp) {
            $paciente = Paciente::where('curp', $curp)->first();

            if ($paciente) {
                return $paciente;
            }
        }

        if ($nss) {
            return Paciente::where('nss', $nss)->first();
        }

        return null;
    }

    /**
     * Compara un domicilio ya guardado contra los datos nuevos capturados.
     *
     * @param  Domicilio  $domicilio  domicilio existente
     * @param  array<string, mixed>  $nuevo  datos recien capturados
     * @return bool true si los cuatro campos son iguales
     */
    private function mismoDomicilio(Domicilio $domicilio, array $nuevo): bool
    {
        foreach (['calle', 'colonia', 'municipio', 'estado'] as $campo) {
            if ($domicilio->{$campo} !== $nuevo[$campo]) {
                return false;
            }
        }

        return true;
    }
}
