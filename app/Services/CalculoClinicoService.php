<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Calculos y normalizaciones clinicas: edad, grupo de edad, talla y fechas
 * flexibles. El cliente nunca decide estos valores, siempre los calcula el servidor.
 */
class CalculoClinicoService
{
    /**
     * Da los años cumplidos entre el nacimiento y la fecha del examen.
     *
     * @param  \DateTimeInterface  $fechaNacimiento  fecha de nacimiento del paciente
     * @param  \DateTimeInterface  $fechaEstudios  fecha en que se realizo el examen
     * @return int|null la edad en años, o null si la fecha es futura o sale de 0-120
     */
    public function edad(\DateTimeInterface $fechaNacimiento, \DateTimeInterface $fechaEstudios): ?int
    {
        $edad = Carbon::instance($fechaNacimiento)->diffInYears(Carbon::instance($fechaEstudios));

        if ($fechaNacimiento > $fechaEstudios || $edad < 0 || $edad > 120) {
            return null;
        }

        return $edad;
    }

    /**
     * Da el rango de edad (para estadisticas y el Excel) a partir de una edad en años.
     *
     * @param  int  $edad  edad en años ya calculada
     * @return string el rango de edad correspondiente
     */
    public function grupoEdad(int $edad): string
    {
        return match (true) {
            $edad < 26 => '< 26',
            $edad <= 35 => '26-35',
            $edad <= 45 => '36-45',
            $edad <= 50 => '46-50',
            default => '> 50',
        };
    }

    /**
     * Pasa la talla capturada a metros: 0.30-3.00 se deja igual, 50-250 se
     * divide entre 100 (asumiendo que se escribio en centimetros).
     *
     * @param  string  $valor  talla tal como la escribio el medico (acepta coma decimal)
     * @return float|null la talla en metros, o null si el valor venia vacio
     *
     * @throws ValidationException si el valor no es un numero o sale de los rangos validos
     */
    public function normalizarTalla(string $valor): ?float
    {
        $texto = trim(str_replace(',', '.', $valor));

        if ($texto === '') {
            return null;
        }

        if (! is_numeric($texto)) {
            throw ValidationException::withMessages(['talla' => 'La talla no es un valor numerico valido.']);
        }

        $numero = (float) $texto;

        if ($numero >= 0.3 && $numero <= 3) {
            return $numero;
        }

        if ($numero >= 50 && $numero <= 250) {
            return round($numero / 100, 2);
        }

        throw ValidationException::withMessages(['talla' => 'La talla debe estar entre 0.30 y 3.00 metros (o 50 y 250 centímetros).']);
    }

    /**
     * Convierte una fecha escrita en AAAA-MM-DD, DD-MM-AAAA o DD/MM/AAAA a un
     * objeto de fecha, rechazando fechas imposibles (como 31 de febrero).
     *
     * @param  string  $valor  fecha capturada por el cliente
     * @param  string  $campo  nombre del campo, para el mensaje de error
     * @return Carbon|null la fecha interpretada, o null si el valor venia vacio
     *
     * @throws ValidationException si el texto no coincide con ningun formato valido
     */
    public function parseFecha(string $valor, string $campo = 'fecha'): ?Carbon
    {
        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $formato) {
            $fecha = Carbon::createFromFormat($formato, $texto);

            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->startOfDay();
            }
        }

        throw ValidationException::withMessages([$campo => 'La fecha no es válida.']);
    }

    /**
     * Normaliza un CURP: mayusculas y solo A-Z, Ñ y 0-9.
     *
     * @param  string|null  $valor  CURP tal como la escribio el usuario
     * @return string|null el CURP normalizado, o null si venia vacio
     */
    public function claveCurp(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return preg_replace('/[^A-ZÑ0-9]/u', '', mb_strtoupper($valor));
    }

    /**
     * Normaliza un NSS: solo digitos (acepta guiones como separadores de captura).
     *
     * @param  string|null  $valor  NSS tal como lo escribio el usuario
     * @return string|null el NSS normalizado, o null si venia vacio
     */
    public function claveNss(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return preg_replace('/\D/', '', $valor);
    }
}
