<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresiones de la auditoria run-1: el cierre de año es el unico borrado irreversible de datos clinicos.
 * Solo cierra años anteriores, borra en un segundo paso con el total y la huella de la descarga,
 * y deja todo en bitacora.
 */
class CierreAnioTest extends TestCase
{
    use RefreshDatabase;

    private const CURP_A = 'GALJ850312HCMRPN09';

    private const CURP_B = 'PELM900101MCMRRR05';

    private User $medico;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->medico = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Alta minima: nombre, CURP y/o NSS y medico dictaminador.
     *
     * @param  array<string, mixed>  $extra  datos que reemplazan o completan los minimos
     * @return array<string, mixed>
     */
    private function alta(array $extra): array
    {
        return array_merge([
            'nombre_completo' => 'Garcia Lopez Juan Carlos',
            'medico_dictaminador' => $this->medico->id,
        ], $extra);
    }

    /** Crea por la API un paciente con su primer expediente. */
    private function crear(array $extra): void
    {
        $this->actingAsUsuario($this->medico)->postJson('/api/v1/expedientes', $this->alta($extra))->assertStatus(201);
    }

    /**
     * Crea dos expedientes y los pasa al año anterior (anio sale de fecha_estudios).
     */
    private function dosExpedientesDelAnioPasado(): int
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['nombre_completo' => 'Perez Lopez Maria', 'curp' => self::CURP_B]);
        $anio = now('America/Mexico_City')->year - 1;
        HistoriaClinica::query()->update(['fecha_estudios' => "{$anio}-03-10"]);

        return $anio;
    }

    /**
     * Descarga el respaldo como lo hace el front y devuelve el JSON completo.
     *
     * @return array<string, mixed>
     */
    private function respaldo(int $anio): array
    {
        $response = $this->actingAsUsuario($this->admin)->get("/api/v1/admin/anios/{$anio}/respaldo");
        $response->assertOk();

        return json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Antes el año en curso se podia borrar y sus folios volvian a empezar en 1.
     */
    public function test_no_se_puede_respaldar_ni_cerrar_el_anio_en_curso(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $anio = now('America/Mexico_City')->year;

        $this->actingAsUsuario($this->admin)
            ->getJson("/api/v1/admin/anios/{$anio}/respaldo")
            ->assertStatus(404);

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['total' => 1, 'huella' => str_repeat('a', 64)])
            ->assertStatus(404);

        $this->assertSame(1, HistoriaClinica::count());
    }

    /**
     * Descargar no debe borrar nada: si el Excel falla, el año sigue intacto. La extraccion masiva queda en bitacora.
     */
    public function test_el_respaldo_no_borra_y_queda_en_bitacora(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $datos = $this->respaldo($anio);

        $this->assertSame(2, $datos['total']);
        $this->assertCount(2, $datos['items']);
        $this->assertSame(64, strlen($datos['huella']));
        $this->assertSame(2, HistoriaClinica::count());
        $this->assertSame(1, Auditoria::where('accion', AccionAuditoria::CierreDeAnio)->count());
    }

    /**
     * El contrato anterior ({confirmar:true}) ya no borra.
     */
    public function test_cerrar_sin_la_huella_de_la_descarga_se_rechaza(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['confirmar' => true])
            ->assertStatus(400);

        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * Sin la huella real no hay prueba de que el respaldo llego completo.
     */
    public function test_cerrar_con_huella_incorrecta_no_borra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $datos = $this->respaldo($anio);

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['total' => 2, 'huella' => str_repeat('0', 64)])
            ->assertStatus(409);

        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * Una edicion posterior no estaria en el Excel descargado.
     */
    public function test_si_el_anio_cambio_despues_de_la_descarga_no_borra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $datos = $this->respaldo($anio);

        $this->travel(5)->seconds();
        HistoriaClinica::where('folio_anual', 1)->first()->update(['empresa' => 'EMPRESA EDITADA']);

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['total' => $datos['total'], 'huella' => $datos['huella']])
            ->assertStatus(409);

        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * El camino normal: borra el año y sus pacientes sin otras visitas, y deja rastro de quien lo hizo.
     */
    public function test_cerrar_con_la_huella_de_la_descarga_borra_y_registra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $datos = $this->respaldo($anio);

        $response = $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['total' => $datos['total'], 'huella' => $datos['huella']])
            ->assertOk()
            ->assertJsonPath('total_borrado', 2)
            ->assertJsonPath('pacientes_borrados', 2);

        $this->assertSame(0, HistoriaClinica::count());
        $this->assertSame(0, Paciente::count());

        // Dos eventos: la descarga y el borrado (en la prueba comparten la misma fecha).
        $detalles = Auditoria::where('accion', AccionAuditoria::CierreDeAnio)->pluck('detalle')->implode(' | ');
        $this->assertStringContainsString('Descarga del respaldo', $detalles);
        $this->assertStringContainsString('se borraron 2 expedientes', $detalles);
    }

    /**
     * Descargar un año completo de datos clinicos es solo de Administracion.
     */
    public function test_un_medico_no_puede_respaldar(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();

        $this->actingAsUsuario($this->medico)
            ->getJson("/api/v1/admin/anios/{$anio}/respaldo")
            ->assertStatus(403);
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
