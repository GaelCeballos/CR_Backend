<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Interpreta el gradiente de aptitud capturado (acepta "2", "G2" o "B2") y da
 * el color que le corresponde para mostrarlo en la interfaz.
 */
class GradienteService
{
    /**
     * @var array<int, string> color hexadecimal por nivel de gradiente
     */
    private const COLORES = [
        1 => '#00B050',
        2 => '#FFFF00',
        3 => '#FF9800',
        4 => '#FF0000',
    ];

    /**
     * Extrae el numero de gradiente (1 a 4) de un texto que puede venir como
     * "2", "G2" o "B2".
     *
     * @param  string  $valor  texto capturado por el medico
     * @return int el nivel de gradiente (1, 2, 3 o 4)
     *
     * @throws ValidationException si el texto no contiene un gradiente valido
     */
    public function parsear(string $valor): int
    {
        if (preg_match('/([1-4])/', $valor, $coincidencia) !== 1) {
            throw ValidationException::withMessages(['gradiente' => 'El gradiente debe ser un número del 1 al 4.']);
        }

        return (int) $coincidencia[1];
    }

    /**
     * Da el color hexadecimal con el que se debe mostrar un nivel de gradiente.
     *
     * @param  int  $gradiente  nivel de gradiente (1 a 4)
     * @return string el color hexadecimal correspondiente
     */
    public function color(int $gradiente): string
    {
        return self::COLORES[$gradiente] ?? self::COLORES[4];
    }
}
