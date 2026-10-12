<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prueba el flujo de login/logout: orden de validacion, mensajes exactos de
 * error, y que un nuevo login invalide el token de la sesion anterior.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Si falta el correo o la contraseña, debe responder el mensaje combinado.
     */
    public function test_login_sin_correo_o_contrasena(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/login', ['perfil' => 'admin']);

        $respuesta->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'Ingresa tu correo y contraseña.');
    }

    /**
     * Correo inexistente o contraseña incorrecta deben dar el mismo mensaje generico.
     */
    public function test_login_con_credenciales_incorrectas(): void
    {
        User::factory()->create(['email' => 'medica@crm-colima.mx']);

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'incorrecta',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('error.message', 'Correo o contraseña incorrectos.');
    }

    /**
     * Una cuenta inactiva no puede iniciar sesion aunque la contraseña sea correcta.
     */
    public function test_login_con_cuenta_inactiva(): void
    {
        User::factory()->inactivo()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'Temporal123',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(403)
            ->assertJsonPath('error.message', 'Tu cuenta está inactiva. Contacta a Administración.');
    }

    /**
     * Si el perfil elegido no coincide con el rol real de la cuenta, debe rechazarse.
     */
    public function test_login_con_perfil_distinto_al_rol(): void
    {
        User::factory()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'Temporal123',
            'perfil' => 'admin',
        ]);

        $respuesta->assertStatus(403)
            ->assertJsonPath('error.message', 'Esta cuenta no tiene acceso de Administración. Ingresa como Servicios Médicos.');
    }

    /**
     * Un login exitoso devuelve el usuario, un token y la ruta de redireccion correcta.
     */
    public function test_login_exitoso_redirige_a_perfil_si_la_contrasena_es_temporal(): void
    {
        User::factory()->conPasswordTemporal()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'Temporal123',
            'perfil' => 'servicios_medicos',
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('redirect', '/perfil')
            ->assertJsonStructure(['user', 'token', 'redirect']);
    }

    /**
     * Si el usuario olvido cerrar sesion en otro equipo, un nuevo login debe
     * invalidar de inmediato el token anterior.
     */
    public function test_un_nuevo_login_invalida_el_token_anterior(): void
    {
        $usuario = User::factory()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $credenciales = [
            'email' => 'medica@crm-colima.mx',
            'password' => 'Temporal123',
            'perfil' => 'servicios_medicos',
        ];

        $primerToken = $this->postJson('/api/v1/auth/login', $credenciales)->json('token');
        $this->postJson('/api/v1/auth/login', $credenciales);

        $this->withHeader('Authorization', "Bearer {$primerToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * Cerrar sesion debe destruir el token con el que se hizo la peticion.
     */
    public function test_logout_revoca_el_token_actual(): void
    {
        $usuario = User::factory()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'medica@crm-colima.mx',
            'password' => 'Temporal123',
            'perfil' => 'servicios_medicos',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(204);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }
}
