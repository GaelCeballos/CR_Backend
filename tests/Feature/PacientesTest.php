<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba el buscador de pacientes y la precarga de datos fijos para
 * pacientes recurrentes.
 */
class PacientesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un expediente base para que exista un paciente que buscar.
     */
    private function crearExpedienteDePrueba(User $medico): void
    {
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', [
            'nombre_completo' => 'Garcia Lopez Juan Carlos',
            'curp' => 'GALJ850312HCMRPN09',
            'nss' => '12345678901',
            'fecha_nacimiento' => '1985-03-12',
            'genero' => 'MASCULINO',
            'telefono' => '3121234567',
            'empresa' => 'CONSORCIO MINERO DEMO',
            'puesto' => 'OPERADOR',
            'bh_grupo_rh' => 'O POSITIVO',
            'ad_opi' => 'NO REACTIVO',
            'ad_coc' => 'NO REACTIVO',
            'ad_thc' => 'NO REACTIVO',
            'ad_bzo' => 'NO REACTIVO',
            'ad_met' => 'NO REACTIVO',
            'ad_amp' => 'NO REACTIVO',
        ]);
    }

    /**
     * El buscador exige al menos 2 caracteres y encuentra por nombre.
     */
    public function test_buscar_pacientes_por_nombre(): void
    {
        $medico = User::factory()->create();
        $this->crearExpedienteDePrueba($medico);

        $this->actingAsUsuario($medico)
            ->getJson('/api/v1/pacientes/buscar?q=garcia')
            ->assertStatus(200)
            ->assertJsonFragment(['curp' => 'GALJ850312HCMRPN09']);
    }

    /**
     * El lookup por CURP exacta precarga los datos fijos de la visita mas reciente.
     */
    public function test_lookup_por_curp_precarga_datos_fijos(): void
    {
        $medico = User::factory()->create();
        $this->crearExpedienteDePrueba($medico);

        $respuesta = $this->actingAsUsuario($medico)
            ->getJson('/api/v1/pacientes/lookup?curp=GALJ850312HCMRPN09');

        $respuesta->assertStatus(200)
            ->assertJsonPath('ultimo_folio', 1)
            ->assertJsonPath('datos.nombre_completo', 'Garcia Lopez Juan Carlos')
            ->assertJsonPath('datos.empresa', 'CONSORCIO MINERO DEMO')
            ->assertJsonPath('datos.puesto', 'OPERADOR');
    }

    /**
     * Si no hay ningun paciente con ese identificador, debe responder 404.
     */
    public function test_lookup_sin_coincidencia_da_404(): void
    {
        $medico = User::factory()->create();

        $this->actingAsUsuario($medico)
            ->getJson('/api/v1/pacientes/lookup?curp=XXXX000000XXXXXX00')
            ->assertStatus(404);
    }

    /**
     * Autentica las peticiones de prueba con un token Sanctum real.
     */
    private function actingAsUsuario(User $usuario): self
    {
        $this->app['auth']->forgetGuards();

        $token = $usuario->createToken('api')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
