<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prueba el límite de accesos para login y cambio de contraseña: limitación por cuenta,
 * por IP, validaciones de entrada y manejo de intentos fallidos.
 */
class LimiteAccesosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El limitador de intentos de login usa la cache, que RefreshDatabase no
     * reinicia: si no se limpia, un test puede heredar los intentos de otro.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->getStore()->flush();
    }

    /**
     * Helper para actuar como un usuario específico y agregar su token de autorización.
     */
    private function actingAsUsuario(User $usuario): self
    {
        $this->app['auth']->forgetGuards();

        $token = $usuario->createToken('api')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Prueba que el límite de intentos de login se aplica por cuenta, independientemente
     * de la IP desde la que se intenta acceder.
     */
    public function test_login_limita_por_cuenta_aunque_cambie_la_ip(): void
    {
        User::factory()->create(['email' => 'medica@crm-colima.mx']);

        for ($i = 1; $i <= 5; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->postJson('/api/v1/auth/login', [
                'email' => 'medica@crm-colima.mx',
                'password' => 'incorrecta',
                'perfil' => 'servicios_medicos',
            ]);

            $respuesta->assertStatus(401);
        }

        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'incorrecta',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    /**
     * Prueba que el límite de intentos de login se aplica por IP, independientemente
     * de la cuenta desde la que se intenta acceder.
     */
    public function test_login_limita_por_ip_aunque_cambie_la_cuenta(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('/api/v1/auth/login', [
                'email' => "cuenta{$i}@crm-colima.mx",
                'password' => 'incorrecta',
                'perfil' => 'servicios_medicos',
            ]);

            $respuesta->assertStatus(401);
        }

        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->postJson('/api/v1/auth/login', [
            'email' => 'otra@crm-colima.mx',
            'password' => 'incorrecta',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(429);
    }

    /**
     * Prueba que un email no válido (no texto) en el login devuelve un error 400.
     */
    public function test_login_con_email_que_no_es_texto_da_400(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => ['a'],
            'password' => 'x',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Prueba que un email demasiado largo en el login devuelve un error 400.
     */
    public function test_login_con_email_demasiado_largo_da_400(): void
    {
        $email = str_repeat('a', 300).'@x.mx';

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'x',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(400);
    }

    /**
     * Un correo inexistente tambien verifica un hash (el señuelo): asi tarda lo
     * mismo que uno real y la respuesta no revela que correos tienen cuenta.
     */
    public function test_login_con_correo_inexistente_verifica_un_hash(): void
    {
        // Mock completo: el HashManager no admite partialMock. make() es el señuelo que se guarda en cache.
        Hash::shouldReceive('make')->andReturn('$argon2id$v=19$m=65536,t=4,p=1$c2VudWVsbw$c2VudWVsbw');
        Hash::shouldReceive('check')->once()->andReturnFalse();

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => 'nadie@crm-colima.mx',
            'password' => 'x',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    /**
     * Prueba que el límite de intentos para cambiar la contraseña se aplica correctamente.
     */
    public function test_cambio_de_password_limita_intentos(): void
    {
        $usuario = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAsUsuario($usuario)
                ->postJson('/api/v1/me/password', [
                    'actual' => 'Incorrecta1',
                    'nueva' => 'NuevaClave123',
                ])
                ->assertStatus(400);
        }

        $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/me/password', [
                'actual' => 'Incorrecta1',
                'nueva' => 'NuevaClave123',
            ])
            ->assertStatus(429);
    }

    /**
     * Prueba que el cambio de contraseña no revela la contraseña actual en caso
     * de error.
     */
    public function test_cambio_de_password_no_revela_la_actual(): void
    {
        $usuario = User::factory()->create(['hashed_password' => Hash::make('Actual12345')]);

        $respuesta = $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/me/password', [
                'actual' => 'Incorrecta1',
                'nueva' => 'Actual12345',
            ]);

        $respuesta->assertStatus(400)
            ->assertJsonPath('error.fields.actual', 'La contraseña actual no es correcta.')
            ->assertJsonMissingPath('error.fields.nueva');
    }

    /**
     * Prueba que el cambio de contraseña no permite establecer una nueva contraseña
     * igual a la actual.
     */
    public function test_cambio_de_password_igual_a_la_actual_se_rechaza(): void
    {
        $usuario = User::factory()->create(['hashed_password' => Hash::make('Actual12345')]);

        $respuesta = $this->actingAsUsuario($usuario)
            ->postJson('/api/v1/me/password', [
                'actual' => 'Actual12345',
                'nueva' => 'Actual12345',
            ]);

        $respuesta->assertStatus(400)
            ->assertJsonPath('error.fields.nueva', 'La nueva contraseña debe ser distinta de la actual.');
    }
}
