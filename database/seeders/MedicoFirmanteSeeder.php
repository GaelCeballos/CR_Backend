<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Da de alta al medico firmante oficial para que aparezca en el selector de
 * medicos. No se espera que inicie sesion: su contraseña es aleatoria y no se
 * comparte con nadie.
 */
class MedicoFirmanteSeeder extends Seeder
{
    /**
     * Inserta la cuenta del firmante oficial si todavia no existe.
     */
    public function run(): void
    {
        $email = 'manuel.coria@crm-colima.mx';

        if (User::where('email', $email)->exists()) {
            $this->command?->info('El firmante oficial ya existe; se omite el seeder.');

            return;
        }

        User::create([
            'email' => $email,
            'hashed_password' => Hash::make((new PasswordGeneratorService())->generar()),
            'nombre_completo' => 'MANUEL ALEJANDRO CORIA SALAS',
            'cedula_profesional' => '14021036',
            'universidad' => 'UNIVERSIDAD DE COLIMA',
            'rol' => 'servicios_medicos',
            'estado' => 'activo',
            'is_temporary_password' => true,
        ]);
    }
}
