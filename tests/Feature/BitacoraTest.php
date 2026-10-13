<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReseteaSecuenciaFolio;
use Tests\TestCase;

/**
 * Prueba que la bitacora solo la pueda consultar un administrador, y que
 * refleje los eventos generados al crear/consultar expedientes.
 */
class BitacoraTest extends TestCase
{
    use RefreshDatabase;
    use ReseteaSecuenciaFolio;

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
