<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de "php artisan db:seed": da de alta las cuentas base
 * que necesita el sistema para funcionar (administrador y firmante oficial).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta, en orden, los seeders base del sistema.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            MedicoFirmanteSeeder::class,
        ]);
    }
}
