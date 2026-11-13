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
 * Regresiones de la auditoria de seguridad run-1 (H01): un alta o una edicion
 * nunca debe pasar los expedientes de un paciente a la identidad de otro, y
 * cualquier cambio de datos fijos de un paciente existente queda en bitacora.
 */
class IdentidadPacienteTest extends TestCase
{
    use RefreshDatabase;

    private const CURP_A = 'GALJ850312HCMRPN09';

    private const CURP_B = 'PELM900101MCMRRR05';

    private const NSS_A = '12345678901';

    private const NSS_B = '10987654321';

    private User $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->medico = User::factory()->create();
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
     * El caso del hallazgo: NSS de un paciente existente con una CURP nueva.
     * Antes se reescribia la CURP y el nombre del paciente existente sin rastro.
     */
    public function test_alta_con_nss_existente_y_curp_distinta_se_rechaza_sin_tocar_al_paciente(): void
    {
        $this->crear(['curp' => self::CURP_A, 'nss' => self::NSS_A]);

        $this->actingAsUsuario($this->medico)
            ->postJson('/api/v1/expedientes', $this->alta([
                'nombre_completo' => 'Perez Lopez Maria',
                'curp' => self::CURP_B,
                'nss' => self::NSS_A,
            ]))
            ->assertStatus(400)
            ->assertJsonPath('error.fields.nss', 'Ese NSS ya está registrado con otra CURP. Verifica la captura.');

        $paciente = Paciente::sole();
        $this->assertSame(self::CURP_A, $paciente->curp);
        $this->assertSame('Garcia Lopez Juan Carlos', $paciente->nombre_completo);
        $this->assertSame(1, HistoriaClinica::count());
    }

    /** La CURP de un paciente con el NSS de otro: antes daba un 409 de "otro equipo". */
    public function test_alta_con_curp_de_un_paciente_y_nss_de_otro_se_rechaza(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['nombre_completo' => 'Perez Lopez Maria', 'curp' => self::CURP_B, 'nss' => self::NSS_B]);

        $this->actingAsUsuario($this->medico)
            ->postJson('/api/v1/expedientes', $this->alta(['curp' => self::CURP_A, 'nss' => self::NSS_B]))
            ->assertStatus(400)
            ->assertJsonPath('error.fields.curp', 'La CURP y el NSS pertenecen a pacientes distintos. Verifica la captura.');

        $this->assertNull(Paciente::where('curp', self::CURP_A)->value('nss'));
        $this->assertSame(2, HistoriaClinica::count());
    }

    /** Una CURP existente con un NSS distinto al guardado tambien se rechaza. */
    public function test_alta_con_curp_existente_y_nss_distinto_se_rechaza(): void
    {
        $this->crear(['curp' => self::CURP_A, 'nss' => self::NSS_A]);

        $this->actingAsUsuario($this->medico)
            ->postJson('/api/v1/expedientes', $this->alta(['curp' => self::CURP_A, 'nss' => self::NSS_B]))
            ->assertStatus(400)
            ->assertJsonPath('error.fields.curp', 'Esa CURP ya está registrada con otro NSS. Verifica la captura.');

        $this->assertSame(self::NSS_A, Paciente::sole()->nss);
    }

    /** Completar un dato que faltaba (el NSS) sigue permitido y no es un conflicto. */
    public function test_alta_completa_el_nss_que_faltaba(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['curp' => self::CURP_A, 'nss' => self::NSS_A]);

        $this->assertSame(self::NSS_A, Paciente::sole()->nss);
        $this->assertSame(2, HistoriaClinica::count());
    }

    /**
     * Decision del dueño (2026-11-12): el alta de un paciente existente sigue
     * corrigiendo sus datos fijos, pero la bitacora guarda el valor anterior.
     */
    public function test_alta_que_corrige_datos_fijos_los_registra_en_bitacora(): void
    {
        $this->crear(['curp' => self::CURP_A, 'nss' => self::NSS_A, 'fecha_nacimiento' => '1985-03-12']);
        $this->crear([
            'nombre_completo' => 'Garcia Lopez Juan Carlo',
            'curp' => self::CURP_A,
            'nss' => self::NSS_A,
            'fecha_nacimiento' => '1985-03-21',
        ]);

        $this->assertSame('Garcia Lopez Juan Carlo', Paciente::sole()->nombre_completo);

        $detalle = Auditoria::where('accion', AccionAuditoria::CreacionDeFolio)->where('folio_anual', 2)->value('detalle');
        $this->assertStringContainsString('Datos del paciente actualizados', $detalle);
        $this->assertStringContainsString('«Garcia Lopez Juan Carlos» → «Garcia Lopez Juan Carlo»', $detalle);
        $this->assertStringContainsString('«1985-03-12» → «1985-03-21»', $detalle);
    }

    /** Volver a capturar los mismos datos no agrega ruido a la bitacora. */
    public function test_alta_sin_cambios_de_paciente_no_agrega_detalle(): void
    {
        $this->crear(['curp' => self::CURP_A, 'fecha_nacimiento' => '1985-03-12']);
        $this->crear(['curp' => self::CURP_A, 'fecha_nacimiento' => '1985-03-12']);

        $detalle = Auditoria::where('accion', AccionAuditoria::CreacionDeFolio)->where('folio_anual', 2)->value('detalle');
        $this->assertStringNotContainsString('Datos del paciente', $detalle);
    }

    /** Una CURP con Ñ es valida (antes strlen contaba 19 bytes). */
    public function test_curp_con_enie_se_acepta(): void
    {
        $this->crear(['curp' => 'MUÑJ850312HCMRPN09']);

        $this->assertSame('MUÑJ850312HCMRPN09', Paciente::sole()->curp);
    }

    /** En la edicion, la CURP de otro paciente da un aviso claro, no el 409 de concurrencia. */
    public function test_edicion_con_curp_de_otro_paciente_se_rechaza(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['nombre_completo' => 'Perez Lopez Maria', 'curp' => self::CURP_B]);
        $clave = now('America/Mexico_City')->year.'-1';

        $this->actingAsUsuario($this->medico)
            ->putJson("/api/v1/expedientes/{$clave}", $this->alta(['curp' => self::CURP_B]))
            ->assertStatus(400)
            ->assertJsonPath('error.fields.curp', 'Esa CURP ya pertenece a otro paciente. Verifica la captura.');

        $this->assertSame(0, Auditoria::where('accion', AccionAuditoria::EdicionDeExpediente)->count());
    }

    /** En la edicion la CURP se normaliza igual que en el alta (antes, minusculas = 500). */
    public function test_edicion_normaliza_la_curp(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $clave = now('America/Mexico_City')->year.'-1';

        $this->actingAsUsuario($this->medico)
            ->putJson("/api/v1/expedientes/{$clave}", $this->alta(['curp' => strtolower(self::CURP_B)]))
            ->assertOk();

        $this->assertSame(self::CURP_B, Paciente::sole()->curp);
        $this->assertSame(1, Auditoria::where('accion', AccionAuditoria::EdicionDeExpediente)->count());
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
