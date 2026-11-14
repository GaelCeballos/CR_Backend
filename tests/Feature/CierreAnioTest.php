<?php

namespace Tests\Feature;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\RespaldoAnio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Regresiones de la auditoria run-1: el cierre de año es el unico borrado irreversible de datos clinicos.
 * Solo cierra años anteriores, respaldar y borrar son acciones separadas, borra solo con un respaldo vigente,
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
    private function descargar(int $anio): array
    {
        $response = $this->actingAsUsuario($this->admin)->get("/api/v1/admin/anios/{$anio}/respaldo");
        $response->assertOk();

        return json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Descarga y confirma el respaldo, como el front cuando el Excel ya se genero. */
    private function respaldar(int $anio): void
    {
        $datos = $this->descargar($anio);

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/respaldo", ['total' => $datos['total'], 'huella' => $datos['huella']])
            ->assertOk();
    }

    /** Pide el borrado escribiendo la confirmacion. */
    private function cerrar(int $anio, ?string $confirmacion = null): TestResponse
    {
        return $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['confirmacion' => $confirmacion ?? "CERRAR {$anio}"]);
    }

    /**
     * Antes el año en curso se podia borrar y sus folios volvian a empezar en 1.
     */
    public function test_no_se_puede_respaldar_ni_cerrar_el_anio_en_curso(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $anio = now('America/Mexico_City')->year;

        $this->actingAsUsuario($this->admin)->getJson("/api/v1/admin/anios/{$anio}/respaldo")->assertStatus(404);
        $this->cerrar($anio)->assertStatus(404);

        $this->assertSame(1, HistoriaClinica::count());
    }

    /**
     * Descargar no debe borrar nada: el admin puede solo respaldar. La extraccion masiva queda en bitacora como respaldo, no como cierre.
     */
    public function test_el_respaldo_no_borra_y_queda_en_bitacora(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $datos = $this->descargar($anio);

        $this->assertSame(2, $datos['total']);
        $this->assertCount(2, $datos['items']);
        $this->assertSame(64, strlen($datos['huella']));
        $this->assertSame(2, HistoriaClinica::count());
        $this->assertSame(1, Auditoria::where('accion', AccionAuditoria::RespaldoDeAnio)->count());
        $this->assertSame(0, Auditoria::where('accion', AccionAuditoria::CierreDeAnio)->count());
    }

    /**
     * Sin un respaldo confirmado no se borra: es la unica copia que quedara de ese año.
     */
    public function test_cerrar_sin_respaldo_no_borra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $this->descargar($anio);

        $this->cerrar($anio)->assertStatus(409)->assertJsonPath('error.message', "Primero descarga el respaldo de {$anio}: solo se borra un año ya respaldado.");

        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * Escribir el año evita borrar el año equivocado con un clic.
     */
    public function test_cerrar_exige_escribir_la_confirmacion(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $this->respaldar($anio);

        $this->cerrar($anio, 'CERRAR '.($anio - 1))->assertStatus(400)->assertJsonPath('error.fields.confirmacion', "Escribe CERRAR {$anio} para confirmar.");
        $this->actingAsUsuario($this->admin)->postJson("/api/v1/admin/anios/{$anio}/cerrar", ['confirmar' => true])->assertStatus(400);

        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * Una huella que no es la del historial actual no prueba que el Excel este completo.
     */
    public function test_confirmar_respaldo_con_huella_incorrecta_se_rechaza(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $this->descargar($anio);

        $this->actingAsUsuario($this->admin)
            ->postJson("/api/v1/admin/anios/{$anio}/respaldo", ['total' => 2, 'huella' => str_repeat('0', 64)])
            ->assertStatus(409);

        $this->assertSame(0, RespaldoAnio::count());
    }

    /**
     * Una edicion posterior al respaldo no estaria en el Excel: hay que respaldar otra vez.
     */
    public function test_si_el_anio_cambio_despues_del_respaldo_no_borra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $this->respaldar($anio);

        $this->travel(5)->seconds();
        HistoriaClinica::where('folio_anual', 1)->first()->update(['empresa' => 'EMPRESA EDITADA']);

        $this->actingAsUsuario($this->admin)->getJson('/api/v1/admin/anios/pendientes')
            ->assertJsonPath('anios.0.respaldo.vigente', false);
        $this->cerrar($anio)->assertStatus(409);
        $this->assertSame(2, HistoriaClinica::count());

        // Con un respaldo nuevo ya se puede.
        $this->respaldar($anio);
        $this->cerrar($anio)->assertOk();
    }

    /**
     * El camino normal: respaldar y, otro dia, borrar el año y sus pacientes sin otras visitas, con rastro de quien lo hizo.
     */
    public function test_cerrar_con_respaldo_vigente_borra_y_registra(): void
    {
        $anio = $this->dosExpedientesDelAnioPasado();
        $this->respaldar($anio);

        $this->actingAsUsuario($this->admin)->getJson('/api/v1/admin/anios/pendientes')
            ->assertJsonPath('anios.0.anio', $anio)
            ->assertJsonPath('anios.0.total', 2)
            ->assertJsonPath('anios.0.respaldo.vigente', true);

        $this->travel(1)->days();
        $this->cerrar($anio)
            ->assertOk()
            ->assertJsonPath('total_borrado', 2)
            ->assertJsonPath('pacientes_borrados', 2);

        $this->assertSame(0, HistoriaClinica::count());
        $this->assertSame(0, Paciente::count());
        $detalle = Auditoria::where('accion', AccionAuditoria::CierreDeAnio)->sole()->detalle;
        $this->assertStringContainsString("Se borraron 2 expedientes de {$anio}", $detalle);
        $this->assertStringContainsString('Respaldo del', $detalle);
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
