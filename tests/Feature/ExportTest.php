<?php

namespace Tests\Feature;

use App\Models\HistoriaClinica;
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
     * Pasa el folio recien creado a ayer. fecha_estudios la pone el reloj de
     * Postgres al insertar, asi que travelTo() no la mueve.
     */
    private function pasarAAyer(): HistoriaClinica
    {
        $historia = HistoriaClinica::sole();
        $historia->update(['fecha_estudios' => now('America/Mexico_City')->subDay()->toDateString()]);

        return $historia->refresh();
    }

    /**
     * Folio creado ayer y editado hoy: el reporte de hoy solo lo trae si se piden
     * los editados (peticion del cliente: por defecto, solo los creados del dia).
     */
    public function test_por_defecto_solo_salen_los_creados_del_dia(): void
    {
        $medico = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->crearExpediente($medico);
        $ayer = $this->pasarAAyer();
        $this->crearExpediente($medico);

        $this->actingAsUsuario($medico)
            ->putJson("/api/v1/expedientes/{$ayer->anio}-{$ayer->folio_anual}", ['nombre_completo' => 'Garcia Lopez Juan Carlos', 'curp' => 'GALJ850312HCMRPN09', 'medico_dictaminador' => $medico->id, 'puesto' => 'SUPERVISOR'])
            ->assertOk();

        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel?creados=0&editados=1')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.values.puesto', 'SUPERVISOR');

        // Ambos: cada folio una sola vez.
        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel?creados=1&editados=1')
            ->assertOk()
            ->assertJsonCount(2, 'items');
    }

    /**
     * Marcar la entrega de resultados (al generar el PDF) mueve updated_at pero no
     * es una edicion: antes metia el folio en el reporte de ese dia.
     */
    public function test_marcar_entrega_no_cuenta_como_edicion(): void
    {
        $medico = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->crearExpediente($medico);
        $h = $this->pasarAAyer();
        $this->actingAsUsuario($admin)->postJson("/api/v1/expedientes/{$h->anio}-{$h->folio_anual}/entrega")->assertOk();

        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel?creados=1&editados=1')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    /**
     * Sin ninguna opcion no hay reporte que armar; y una fecha mal escrita da 400, no 500.
     */
    public function test_valida_opciones_y_fecha(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel?creados=0&editados=0')
            ->assertStatus(400)
            ->assertJsonPath('error.fields.creados', 'Elige al menos creados o editados.');

        $this->actingAsUsuario($admin)->getJson('/api/v1/export/excel?fecha=09/11/2026')
            ->assertStatus(400);
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
