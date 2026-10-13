<?php

namespace App\Services;

/**
 * Genera contraseñas temporales aleatorias para cuentas nuevas o restablecidas.
 */
class PasswordGeneratorService
{
    private const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const MINUSCULAS = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITOS = '23456789';

    private const SIMBOLOS = '!#$%&*+-=?@_';

    private const LONGITUD = 12;

    /**
     * Construye una contraseña de 12 caracteres con aleatoriedad criptografica
     * (random_int), que siempre incluye al menos una mayuscula, una minuscula, un
     * digito y un simbolo, sin usar caracteres ambiguos (0, O, 1, l, I).
     *
     * @return string la contraseña temporal generada
     */
    public function generar(): string
    {
        $alfabetos = [self::MAYUSCULAS, self::MINUSCULAS, self::DIGITOS, self::SIMBOLOS];
        $todas = implode('', $alfabetos);

        // Un caracter garantizado de cada alfabeto, para cumplir el requisito de mezcla.
        $caracteres = array_map(
            fn (string $alfabeto) => $alfabeto[random_int(0, strlen($alfabeto) - 1)],
            $alfabetos,
        );

        // Rellena el resto de la longitud tomando caracteres de cualquier alfabeto.
        for ($i = count($caracteres); $i < self::LONGITUD; $i++) {
            $caracteres[] = $todas[random_int(0, strlen($todas) - 1)];
        }

        // Fisher-Yates con random_int para que el orden final tambien sea impredecible.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
