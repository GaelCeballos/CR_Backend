<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /pacientes/identidad alimenta la ventana de confirmacion antes de guardar:
 * debe decir a quien pertenecen la CURP y el NSS para que el medico vea si el folio
 * se agrega al historial de otra persona (fusiones de pacientes, nov-2026).
 */
class PacienteIdentidadTest extends TestCase
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
     * Autentica las peticiones de prueba con un token Sanctum real.
     */
    private function actingAsUsuario(User $usuario): self
    {
        $this->app['auth']->forgetGuards();

        $token = $usuario->createToken('api')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Un paciente nuevo no debe mostrar avisos: nada coincide.
     */
    public function test_sin_coincidencias_devuelve_nulos(): void
    {
        $this->actingAsUsuario($this->medico)
            ->getJson('/api/v1/pacientes/identidad?curp='.self::CURP_A.'&nss='.self::NSS_A)
            ->assertOk()
            ->assertJsonPath('por_curp', null)
            ->assertJsonPath('por_nss', null)
            ->assertJsonPath('expedientes_del_paciente', null);
    }

    /**
     * El caso que fusionaba pacientes: la ventana debe poder decir de quien es cada clave.
     */
    public function test_curp_de_un_paciente_y_nss_de_otro(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['nombre_completo' => 'Perez Lopez Maria', 'curp' => self::CURP_B, 'nss' => self::NSS_B]);

        $response = $this->actingAsUsuario($this->medico)
            ->getJson('/api/v1/pacientes/identidad?curp='.self::CURP_A.'&nss='.self::NSS_B);

        $response->assertJsonPath('por_curp.nombre_completo', 'Garcia Lopez Juan Carlos')
            ->assertJsonPath('por_nss.nombre_completo', 'Perez Lopez Maria')
            ->assertJsonPath('por_curp.expedientes', 1)
            ->assertJsonPath('por_curp.es_este', false);

        $this->assertNotSame($response->json('por_curp.id'), $response->json('por_nss.id'));
    }

    /**
     * Al editar, la CURP del propio paciente no es "de otra persona"; y el aviso dice
     * a cuantos expedientes afecta el cambio.
     */
    public function test_con_clave_del_expediente_marca_es_este(): void
    {
        $this->crear(['curp' => self::CURP_A]);
        $this->crear(['curp' => self::CURP_A]);

        $clave = now('America/Mexico_City')->year.'-1';

        $response = $this->actingAsUsuario($this->medico)
            ->getJson('/api/v1/pacientes/identidad?curp='.self::CURP_A."&clave={$clave}");

        $response->assertJsonPath('por_curp.es_este', true)
            ->assertJsonPath('por_curp.expedientes', 2)
            ->assertJsonPath('expedientes_del_paciente', 2);
    }

    /**
     * Mientras la CURP no esta completa no se busca: evita avisos falsos al escribir.
     */
    public function test_curp_incompleta_no_busca(): void
    {
        $this->crear(['curp' => self::CURP_A]);

        $response = $this->actingAsUsuario($this->medico)
            ->getJson('/api/v1/pacientes/identidad?curp='.substr(self::CURP_A, 0, 10));

        $response->assertJsonPath('por_curp', null);
    }

    /**
     * Devuelve nombres de pacientes: solo con sesion.
     */
    public function test_requiere_sesion(): void
    {
        $this->getJson('/api/v1/pacientes/identidad?curp='.self::CURP_A.'&nss='.self::NSS_A)
            ->assertStatus(401);
    }

    /**
     * 00000000000 se usaba como "sin NSS" y juntaba a varias personas en un paciente.
     */
    public function test_alta_con_nss_de_digitos_iguales_se_rechaza(): void
    {
        $this->actingAsUsuario($this->medico)
            ->postJson('/api/v1/expedientes', $this->alta(['nss' => '00000000000']))
            ->assertStatus(400)
            ->assertJsonPath('error.fields.nss', 'El NSS no puede tener todos los dígitos iguales. Si no lo tienes, déjalo vacío.');

        $this->assertSame(0, Paciente::count());
    }
}
