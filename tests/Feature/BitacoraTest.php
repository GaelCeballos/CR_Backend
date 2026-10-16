<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba que la bitacora solo la pueda consultar un administrador, y que
 * refleje los eventos generados al crear/consultar expedientes.
 */
class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un usuario de servicios medicos no puede leer la bitacora.
     */
    public function test_servicios_medicos_no_puede_leer_la_bitacora(): void
    {
        $medico = User::factory()->create();

        $this->actingAsUsuario($medico)->getJson('/api/v1/bitacora')->assertStatus(403);
    }

    /**
     * Un admin puede leer la bitacora y ve los eventos ya generados.
     */
    public function test_admin_puede_leer_la_bitacora(): void
    {
        $medico = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', [
            'nombre_completo' => 'Garcia Lopez Juan Carlos',
            'curp' => 'GALJ850312HCMRPN09',
            'fecha_nacimiento' => '1985-03-12',
            'genero' => 'MASCULINO',
            'originario' => 'COLIMA',
            'domicilio_calle' => 'AV REFORMA 123',
            'domicilio_colonia' => 'CENTRO',
            'domicilio_municipio' => 'COLIMA',
            'domicilio_estado' => 'COLIMA',
            'telefono' => '3121234567',
            'religion' => 'Ninguna',
            'estado_civil' => 'SOLTERO',
            'empresa' => 'CONSORCIO MINERO DEMO',
            'puesto' => 'OPERADOR',
            'tipo_examen_motivo' => 'INGRESO / EVALUACIÓN INICIAL',
            'tipo_examen_categoria' => 'PERSONAL GENERAL',
            'antiguedad_empresa' => '2 AÑOS',
            'antiguedad_puesto' => '1 AÑO',
            'exposicion_poe' => 'NINGUNA',
            'medico_dictaminador' => $medico->id,
            'bh_grupo_rh' => 'O POSITIVO',
            'ad_opi' => 'NO REACTIVO',
            'ad_coc' => 'NO REACTIVO',
            'ad_thc' => 'NO REACTIVO',
            'ad_bzo' => 'NO REACTIVO',
            'ad_met' => 'NO REACTIVO',
            'ad_amp' => 'NO REACTIVO',
        ]);

        $respuesta = $this->actingAsUsuario($admin)->getJson('/api/v1/bitacora');

        $respuesta->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.accion', 'Creación de Folio');
    }

    /**
     * Autentica las peticiones de prueba con un token Sanctum real.
     */
    private function actingAsUsuario(User $usuario): self
    {
        // Evita que el guard de Sanctum siga devolviendo el usuario de una
        // llamada anterior dentro de la misma prueba.
        $this->app['auth']->forgetGuards();

        $token = $usuario->createToken('api')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
