<?php

namespace App\Services;

use App\Models\Domicilio;
use App\Models\Paciente;
use Illuminate\Support\Facades\DB;
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
     * crea uno nuevo. Si existe, actualiza sus datos fijos con los que llegaron
     * (los medicos corrigen nombre o nacimiento en la visita nueva); quien llama
     * registra en bitacora lo que cambio (ver Paciente::getChanges/getPrevious).
     * Si la CURP y el NSS apuntan a pacientes distintos, o el paciente encontrado
     * ya tiene guardada otra CURP u otro NSS, se rechaza: fusionarlos pasaria los
     * expedientes de una persona a la identidad de otra (auditoria run-1, H01).
     * Debe llamarse dentro de una transaccion: el advisory lock por CURP/NSS
     * evita que dos altas simultaneas del mismo paciente nuevo lo creen las
     * dos (la segunda chocaria con pacientes_curp_uq / pacientes_nss_uq).
     *
     * @param  array<string, mixed>  $datos  curp, nss, nombre_completo, fecha_nacimiento, genero, originario
     * @return Paciente el paciente encontrado o recien creado
     *
     * @throws ValidationException si las claves tienen mal formato o pertenecen a pacientes distintos
     */
    public function upsert(array $datos): Paciente
    {
        [$curp, $nss] = $this->clavesNormalizadas($datos);
        $this->bloquearClaves($curp, $nss);

        $porCurp = $curp !== null ? Paciente::where('curp', $curp)->first() : null;
        $porNss = $nss !== null ? Paciente::where('nss', $nss)->first() : null;

        if ($porCurp && $porNss && $porCurp->id !== $porNss->id) {
            throw ValidationException::withMessages([
                'curp' => 'La CURP y el NSS pertenecen a pacientes distintos. Verifica la captura.',
            ]);
        }

        $paciente = $porCurp ?? $porNss;

        // Coincide una clave pero la otra ya esta guardada con otro valor: es otra
        // persona o un error de captura, nunca se sobrescribe en silencio.
        if ($paciente && $curp !== null && $paciente->curp !== null && $paciente->curp !== $curp) {
            throw ValidationException::withMessages([
                'nss' => 'Ese NSS ya está registrado con otra CURP. Verifica la captura.',
            ]);
        }

        if ($paciente && $nss !== null && $paciente->nss !== null && $paciente->nss !== $nss) {
            throw ValidationException::withMessages([
                'curp' => 'Esa CURP ya está registrada con otro NSS. Verifica la captura.',
            ]);
        }

        $atributos = $this->atributos($datos, $curp, $nss);

        if ($paciente) {
            $paciente->update($atributos);

            return $paciente;
        }

        return Paciente::create($atributos);
    }

    /**
     * Actualiza los datos fijos del paciente desde la edicion (PUT) de uno de sus
     * expedientes, con la misma normalizacion que el alta. Antes la CURP llegaba
     * en crudo (minusculas = 500) y la de otro paciente daba un 409 engañoso.
     * Debe llamarse dentro de una transaccion (usa advisory locks).
     *
     * @param  Paciente  $paciente  paciente del expediente que se edita
     * @param  array<string, mixed>  $datos  curp, nss, nombre_completo, fecha_nacimiento, genero, originario
     *
     * @throws ValidationException si las claves tienen mal formato o ya pertenecen a otro paciente
     */
    public function actualizarDatosFijos(Paciente $paciente, array $datos): void
    {
        [$curp, $nss] = $this->clavesNormalizadas($datos);
        $this->bloquearClaves($curp, $nss);

        foreach (['curp' => $curp, 'nss' => $nss] as $campo => $clave) {
            if ($clave !== null && Paciente::where($campo, $clave)->whereKeyNot($paciente->id)->exists()) {
                throw ValidationException::withMessages([
                    $campo => $campo === 'curp'
                        ? 'Esa CURP ya pertenece a otro paciente. Verifica la captura.'
                        : 'Ese NSS ya pertenece a otro paciente. Verifica la captura.',
                ]);
            }
        }

        $paciente->update($this->atributos($datos, $curp, $nss));
    }

    /**
     * Normaliza y valida la CURP y el NSS capturados.
     *
     * @param  array<string, mixed>  $datos  payload con curp y nss opcionales
     * @return array{0: string|null, 1: string|null} CURP y NSS normalizados
     *
     * @throws ValidationException si alguna clave no tiene la longitud correcta
     */
    private function clavesNormalizadas(array $datos): array
    {
        $curp = $this->calculos->claveCurp($datos['curp'] ?? null);
        $nss = $this->calculos->claveNss($datos['nss'] ?? null);

        // mb_strlen: con strlen una CURP con Ñ contaba 19 bytes y se rechazaba.
        if ($curp !== null && mb_strlen($curp) !== 18) {
            throw ValidationException::withMessages(['curp' => 'La CURP debe tener 18 caracteres.']);
        }

        if ($nss !== null && strlen($nss) !== 11) {
            throw ValidationException::withMessages(['nss' => 'El NSS debe tener 11 dígitos.']);
        }

        // 00000000000, 11111111111…: se usaban como "sin NSS" y juntaban a varias
        // personas en un mismo paciente. Si no se tiene, el NSS se deja vacio.
        if ($nss !== null && preg_match('/^(\d)\1{10}$/', $nss)) {
            throw ValidationException::withMessages(['nss' => 'El NSS no puede tener todos los dígitos iguales. Si no lo tienes, déjalo vacío.']);
        }

        return [$curp, $nss];
    }

    /**
     * Toma los advisory locks de la transaccion por CURP y NSS.
     *
     * @param  string|null  $curp  CURP normalizada
     * @param  string|null  $nss  NSS normalizado
     */
    private function bloquearClaves(?string $curp, ?string $nss): void
    {
        // Siempre en el mismo orden (CURP y luego NSS) para que dos altas no se
        // queden esperando una a la otra. La segunda alta espera aqui hasta que
        // la primera confirme, y entonces ya encuentra al paciente que esta creo.
        foreach (array_filter(['curp' => $curp, 'nss' => $nss]) as $tipo => $clave) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["paciente:{$tipo}:{$clave}"]);
        }
    }

    /**
     * Datos fijos a guardar; los vacios no borran lo que ya habia.
     *
     * @param  array<string, mixed>  $datos  payload del paciente
     * @param  string|null  $curp  CURP normalizada
     * @param  string|null  $nss  NSS normalizado
     * @return array<string, mixed> atributos no nulos
     */
    private function atributos(array $datos, ?string $curp, ?string $nss): array
    {
        return array_filter([
            'nombre_completo' => $datos['nombre_completo'] ?? null,
            'curp' => $curp,
            'nss' => $nss,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'genero' => $datos['genero'] ?? null,
            'originario' => $datos['originario'] ?? null,
        ], fn ($valor) => $valor !== null);
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
