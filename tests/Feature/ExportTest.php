<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba los endpoints que sustituyen la descarga binaria de PDF y Excel:
 * son admin-only y devuelven los mismos datos que ya arma el frontend.
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    /** Clave de ruta "{anio}-{folio_anual}" para un folio creado hoy en una prueba. */
    private function clave(int $folioAnual): string
    {
        return now('America/Mexico_City')->year."-{$folioAnual}";
    }

    /**
     * Crea un expediente de prueba, autenticado como el medico dado.
     */
    private function crearExpediente(User $medico): void
    {
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', [
            'nombre_completo' => 'Garcia Lopez Juan Carlos',
            'curp' => 'GALJ850312HCMRPN09',
            'empresa' => 'CONSORCIO MINERO DEMO',
        ]);
    }

    /**
     * Un medico no puede pedir los datos para PDF ni para Excel.
     */
    public function test_servicios_medicos_no_puede_exportar(): void
    {
        $medico = User::factory()->create();
        $this->crearExpediente($medico);

        $this->actingAsUsuario($medico)
            ->getJson('/api/v1/expedientes/'.$this->clave(1).'/pdf?plantilla=PECO')
            ->assertStatus(403);

        $this->actingAsUsuario($medico)
            ->getJson('/api/v1/export/excel')
            ->assertStatus(403);
    }

    /**
     * Un admin puede pedir los datos para el PDF de un expediente, y queda
     * registrado en la bitacora.
     */
    public function test_admin_puede_pedir_datos_para_pdf(): void
    {
        $medico = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->crearExpediente($medico);

        $respuesta = $this->actingAsUsuario($admin)
            ->getJson('/api/v1/expedientes/'.$this->clave(1).'/pdf?plantilla=PECO');

        $respuesta->assertStatus(200)
            ->assertJsonPath('plantilla', 'PECO')
            ->assertJsonPath('folio', 1);

        $this->assertDatabaseHas('auditoria', [
            'anio' => now('America/Mexico_City')->year,
            'folio_anual' => 1,
            'accion' => 'Consulta',
            'detalle' => 'Descarga de datos para PDF (PECO)',
        ]);
    }

    /**
     * Un admin puede pedir los datos de la jornada de hoy para el Excel.
     */
    public function test_admin_puede_pedir_datos_de_la_jornada(): void
    {
        $medico = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->crearExpediente($medico);

        $respuesta = $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel');

        $respuesta->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.folio', 1)
            ->assertJsonPath('items.0.values.nombre_completo', 'Garcia Lopez Juan Carlos')
            ->assertJsonPath('items.0.creado_por.nombre', $medico->nombre_completo);
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
