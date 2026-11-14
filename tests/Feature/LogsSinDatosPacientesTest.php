<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regresiones de la auditoria de seguridad run-1 (H12, H13): el log de produccion no debe guardar datos de pacientes ni llenarse con errores esperados.
 */
class LogsSinDatosPacientesTest extends TestCase
{
    use RefreshDatabase;

    private User $medico;

    private array $errores = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->medico = User::factory()->create();

        // Captura lo que se escribiria en el log, sin depender del canal configurado.
        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            if ($e->level === 'error') {
                $this->errores[] = $e->message.' '.json_encode($e->context);
            }
        });
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
     * Antes daba 500 y una traza con el SQL.
     */
    public function test_token_malformado_da_401_sin_error_en_log(): void
    {
        $this->withHeader('Authorization', 'Bearer abc|x')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertSame([], $this->errores);
    }

    /**
     * La raiz daba 500 con traza en el log; ahora responde JSON y sin abrir sesion.
     */
    public function test_raiz_responde_json(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('estado', 'ok')
            ->assertCookieMissing(config('session.cookie'));
    }

    /**
     * Empresa excede varchar(255): el error de Postgres no debe llevar los valores al log y se registra una sola vez.
     */
    public function test_error_de_base_no_guarda_datos_del_paciente(): void
    {
        $this->actingAsUsuario($this->medico)
            ->postJson('/api/v1/expedientes', [
                'nombre_completo' => 'Garcia Lopez Juan Carlos',
                'medico_dictaminador' => $this->medico->id,
                'curp' => 'GALJ850312HCMRPN09',
                'telefono' => '3121234567',
                'empresa' => str_repeat('X', 300),
            ])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertCount(1, $this->errores);
        $this->assertStringNotContainsString('3121234567', $this->errores[0]);
        $this->assertStringNotContainsString('Garcia Lopez', $this->errores[0]);
        $this->assertStringNotContainsString(str_repeat('X', 300), $this->errores[0]);
    }

    /**
     * Un error de negocio (ApiException) no es una falla.
     */
    public function test_credenciales_incorrectas_no_escriben_error(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->medico->email,
            'password' => 'Incorrecta#2026',
            'perfil' => 'servicios_medicos',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->assertSame([], $this->errores);
    }
}
