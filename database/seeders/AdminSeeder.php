<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea la primera cuenta de administrador del sistema, con una contraseña
 * temporal aleatoria que se imprime una sola vez en la consola.
 */
class AdminSeeder extends Seeder
{
    /**
     * Inserta la cuenta admin inicial si todavia no existe ninguna.
     */
    public function run(): void
    {
        if (User::where('rol', 'admin')->exists()) {
            $this->command?->info('Ya existe al menos un administrador; se omite el seeder.');

            return;
        }

        $temporaryPassword = (new PasswordGeneratorService)->generar();

        User::create([
            'email' => 'admin@crm-colima.mx',
            'hashed_password' => Hash::make($temporaryPassword),
            'nombre_completo' => 'ADMINISTRADOR',
            'rol' => 'admin',
            'estado' => 'activo',
            'is_temporary_password' => true,
        ]);

        $this->command?->warn("Cuenta admin creada: admin@crm-colima.mx / {$temporaryPassword}");
        $this->command?->warn('Guarda esta contraseña ahora: no se volverá a mostrar.');
    }
}
