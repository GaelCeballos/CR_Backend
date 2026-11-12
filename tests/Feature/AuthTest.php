<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
     * El limitador de intentos de login usa la cache, que RefreshDatabase no
     * reinicia: si no se limpia, un test puede heredar los intentos de otro.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->getStore()->flush();
    }

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

        // El equipo que se quedo con el token viejo debe saber por que lo sacaron.
        $this->withHeader('Authorization', "Bearer {$primerToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REPLACED')
            ->assertJsonPath('error.message', 'Tu sesión se cerró porque tu cuenta inició sesión en otro equipo o pestaña.');
    }

    /**
     * Tras una hora sin actividad la sesion vence y el error lo explica.
     */
    public function test_la_sesion_vence_tras_una_hora_sin_actividad(): void
    {
        $token = $this->iniciarSesion();

        $this->travel(61)->minutes();

        $this->conToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    /**
     * Cada peticion cuenta como actividad: trabajando sin pausas largas, la
     * sesion sigue viva mas alla de la primera hora.
     */
    public function test_la_actividad_extiende_la_sesion(): void
    {
        $token = $this->iniciarSesion();

        $this->travel(50)->minutes();
        $this->conToken($token)->getJson('/api/v1/auth/me')->assertStatus(200);

        $this->travel(50)->minutes();
        $this->conToken($token)->getJson('/api/v1/auth/me')->assertStatus(200);
    }

    /**
     * La revision periodica (GET /auth/sesion) no cuenta como actividad: una
     * pestaña olvidada debe vencer aunque siga preguntando.
     */
    public function test_la_revision_periodica_no_extiende_la_sesion(): void
    {
        $token = $this->iniciarSesion();

        $this->travel(50)->minutes();
        $this->conToken($token)->getJson('/api/v1/auth/sesion')
            ->assertStatus(200)
            ->assertJsonStructure(['vence_en']);

        $this->travel(15)->minutes();
        $this->conToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    /**
     * Aunque haya actividad constante, la sesion no pasa del tope de 8 horas.
     */
    public function test_la_sesion_no_pasa_del_tope_aunque_haya_actividad(): void
    {
        $token = $this->iniciarSesion();

        for ($i = 0; $i < 9; $i++) {
            $this->travel(50)->minutes();
            $this->conToken($token)->getJson('/api/v1/auth/me')->assertStatus(200);
        }

        // 9 x 50 = 450 min; 40 min mas = 490 min desde el login, pasa el tope de 480.
        $this->travel(40)->minutes();
        $this->conToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    /**
     * Un token que nunca existio da el mensaje generico.
     */
    public function test_token_inexistente_da_no_has_iniciado_sesion(): void
    {
        $this->conToken('999|tokenquenoexiste')->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * La llave del borrador llega en el login y en /auth/me, es la misma en cada
     * login (para descifrar un borrador previo), no viaja dentro de "user" y en
     * la base se guarda cifrada.
     */
    public function test_llave_del_borrador_es_estable_y_se_guarda_cifrada(): void
    {
        $usuario = User::factory()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        $primera = $this->postJson('/api/v1/auth/login', $this->credenciales());
        $llave = $primera->json('llave_borrador');

        $this->assertSame(32, strlen(base64_decode($llave, true)));
        $this->assertArrayNotHasKey('llave_borrador', $primera->json('user'));

        $segunda = $this->postJson('/api/v1/auth/login', $this->credenciales());
        $this->assertSame($llave, $segunda->json('llave_borrador'));

        $this->conToken($segunda->json('token'))->getJson('/api/v1/auth/me')
            ->assertJsonPath('llave_borrador', $llave);

        $enBase = DB::table('users')->where('id', $usuario->id)->value('llave_borrador');
        $this->assertNotSame($llave, $enBase);
    }

    /**
     * Los tokens vencidos hace mas de un dia se borran en el siguiente login.
     */
    public function test_el_login_purga_tokens_vencidos_hace_mas_de_un_dia(): void
    {
        $this->iniciarSesion();
        $this->travel(2)->days();
        $this->postJson('/api/v1/auth/login', $this->credenciales());

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    /**
     * Crea la cuenta de prueba e inicia sesion.
     *
     * @return string token en texto plano
     */
    private function iniciarSesion(): string
    {
        User::factory()->create([
            'email' => 'medica@crm-colima.mx',
            'hashed_password' => Hash::make('Temporal123'),
        ]);

        return $this->postJson('/api/v1/auth/login', $this->credenciales())->json('token');
    }

    /**
     * @return array<string, string>
     */
    private function credenciales(): array
    {
        return ['email' => 'medica@crm-colima.mx', 'password' => 'Temporal123', 'perfil' => 'servicios_medicos'];
    }

    /**
     * Prepara una peticion con el token. Olvida el usuario que el guard cacheo en
     * la peticion anterior: en la app real cada peticion vuelve a validar el token.
     */
    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Cerrar sesion debe destruir el token con el que se hizo la peticion.
     *
     * Se verifica contra la base de datos (no con una segunda peticion HTTP):
     * dentro de un mismo metodo de prueba, el guard de autenticacion cachea el
     * usuario resuelto y una segunda llamada no refleja la revocacion aunque
     * en una app real cada peticion si la vea de inmediato.
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

        $this->assertSame(0, $usuario->tokens()->count());
    }
}
