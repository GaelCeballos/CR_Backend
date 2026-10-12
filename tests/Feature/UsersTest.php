<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba la gestion de usuarios: quien puede hacer que, y las salvaguardas
 * que evitan dejar el sistema sin administradores o bloquear a un medico.
 */
class UsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mientras la contraseña sea temporal, solo /me y /me/password son accesibles.
     */
    public function test_password_temporal_bloquea_rutas_que_no_son_perfil(): void
    {
        $usuario = User::factory()->admin()->conPasswordTemporal()->create();

        $this->actingAsUsuario($usuario)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');
    }

    /**
     * Un usuario de servicios medicos no puede dar de alta cuentas.
     */
    public function test_servicios_medicos_no_puede_crear_usuarios(): void
    {
        $medico = User::factory()->create();

        $this->actingAsUsuario($medico)
            ->postJson('/api/v1/users', [
                'nombre_completo' => 'Nueva Persona',
                'email' => 'nueva@crm-colima.mx',
                'rol' => 'servicios_medicos',
                'cedula_profesional' => '11112222',
                'universidad' => 'Universidad de Colima',
            ])
            ->assertStatus(403);
    }

    /**
     * Un admin puede crear cuentas; si no envia contraseña, el servidor genera una.
     */
    public function test_admin_puede_crear_usuario_y_recibe_contrasena_temporal(): void
    {
        $admin = User::factory()->admin()->create();

        $respuesta = $this->actingAsUsuario($admin)->postJson('/api/v1/users', [
            'nombre_completo' => 'Ma. Fernanda Ruiz',
            'email' => 'fernanda@crm-colima.mx',
            'rol' => 'servicios_medicos',
            'cedula_profesional' => '87654321',
            'universidad' => 'Universidad de Guadalajara',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('user.nombre_completo', 'MA. FERNANDA RUIZ')
            ->assertJsonPath('user.is_temporary_password', true)
            ->assertJsonStructure(['temporary_password']);
    }

    /**
     * No se puede dar de alta una cuenta con un correo que ya existe.
     */
    public function test_no_se_puede_crear_usuario_con_correo_duplicado(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'repetido@crm-colima.mx']);

        $this->actingAsUsuario($admin)->postJson('/api/v1/users', [
            'nombre_completo' => 'Otra Persona',
            'email' => 'repetido@crm-colima.mx',
            'rol' => 'servicios_medicos',
            'cedula_profesional' => '11112222',
            'universidad' => 'Universidad de Colima',
        ])->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
    }

    /**
     * Nadie puede desactivar su propia cuenta, ni siquiera un admin.
     */
    public function test_admin_no_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUsuario($admin)->putJson("/api/v1/users/{$admin->id}", [
            'nombre_completo' => $admin->nombre_completo,
            'email' => $admin->email,
            'rol' => 'admin',
            'estado' => 'inactivo',
        ])->assertStatus(403)->assertJsonPath('error.message', 'No puedes desactivar tu propia cuenta.');
    }

    /**
     * Nadie puede quitarse a si mismo el rol de administrador.
     */
    public function test_admin_no_puede_quitarse_su_propio_rol(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsUsuario($admin)->putJson("/api/v1/users/{$admin->id}", [
            'nombre_completo' => $admin->nombre_completo,
            'email' => $admin->email,
            'rol' => 'servicios_medicos',
            'cedula_profesional' => '12345678',
            'universidad' => 'Universidad de Colima',
            'estado' => 'activo',
        ])->assertStatus(403)->assertJsonPath('error.message', 'No puedes quitarte el rol de Administrador a ti mismo.');
    }

    /**
     * Degradar al ultimo admin activo desde otra cuenta de admin debe rechazarse.
     */
    public function test_no_puede_quedar_el_sistema_sin_ningun_admin_activo(): void
    {
        $adminQueEdita = User::factory()->admin()->create();
        $unicoOtroAdmin = User::factory()->admin()->create();

        $this->actingAsUsuario($adminQueEdita)->putJson("/api/v1/users/{$unicoOtroAdmin->id}", [
            'nombre_completo' => $unicoOtroAdmin->nombre_completo,
            'email' => $unicoOtroAdmin->email,
            'rol' => 'servicios_medicos',
            'cedula_profesional' => '12345678',
            'universidad' => 'Universidad de Colima',
            'estado' => 'activo',
        ])->assertStatus(409)->assertJsonPath('error.message', 'Debe quedar al menos un Administrador activo.');
    }

    /**
     * El selector de medicos lo puede leer cualquier usuario autenticado, no solo admin.
     */
    public function test_cualquier_usuario_autenticado_puede_leer_el_selector_de_medicos(): void
    {
        $medico = User::factory()->create(['nombre_completo' => 'LAURA GÓMEZ RUIZ']);

        $this->actingAsUsuario($medico)
            ->getJson('/api/v1/users?vista=selector')
            ->assertStatus(200)
            ->assertJsonFragment(['nombre_completo' => 'LAURA GÓMEZ RUIZ']);
    }

    /**
     * El restablecimiento de contraseña de otra cuenta solo lo puede hacer un admin,
     * y la contraseña anterior deja de funcionar al instante (se revocan sus tokens).
     */
    public function test_admin_puede_restablecer_la_contrasena_de_un_medico(): void
    {
        $admin = User::factory()->admin()->create();
        $medico = User::factory()->create();

        $tokenMedico = $medico->createToken('api')->plainTextToken;

        $respuesta = $this->actingAsUsuario($admin)
            ->postJson("/api/v1/users/{$medico->id}/reset-password");

        $respuesta->assertStatus(200)->assertJsonStructure(['temporary_password']);

        $this->withHeader('Authorization', "Bearer {$tokenMedico}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * Autentica las peticiones de prueba con un token Sanctum real, igual que
     * lo haria un cliente HTTP normal (en vez de Sanctum::actingAs).
     */
    private function actingAsUsuario(User $usuario): self
    {
        $token = $usuario->createToken('api')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
