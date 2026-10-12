<?php

namespace App\Services;

/**
 * Puerto 1:1 de generarPasswordTemporal() (spec §3.1), pero en el servidor:
 * 12 caracteres, aleatoriedad criptografica (random_int), al menos una mayuscula,
 * minuscula, digito y simbolo; sin caracteres ambiguos (0 O 1 l I).
 */
class PasswordGeneratorService
{
    private const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const MINUSCULAS = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITOS = '23456789';

    private const SIMBOLOS = '!#$%&*+-=?@_';

    private const LONGITUD = 12;

    public function generar(): string
    {
        $alfabetos = [self::MAYUSCULAS, self::MINUSCULAS, self::DIGITOS, self::SIMBOLOS];
        $todas = implode('', $alfabetos);

        $caracteres = array_map(
            fn (string $alfabeto) => $alfabeto[random_int(0, strlen($alfabeto) - 1)],
            $alfabetos,
        );

        for ($i = count($caracteres); $i < self::LONGITUD; $i++) {
            $caracteres[] = $todas[random_int(0, strlen($todas) - 1)];
        }

        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }
}
