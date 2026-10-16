<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba el ciclo de vida de un expediente: creacion con calculos
 * automaticos, consulta, edicion y el registro correspondiente en bitacora.
 */
class ExpedientesTest extends TestCase
{
    use RefreshDatabase;

    /** Clave de ruta "{anio}-{folio_anual}" para un folio creado hoy en una prueba. */
    private function clave(int $folioAnual): string
    {
        return now('America/Mexico_City')->year."-{$folioAnual}";
    }

    /**
     * Datos minimos de un expediente nuevo, listos para extender en cada prueba.
     * Incluye los campos de "Datos Generales" (obligatorios) y al propio
     * medico como "medico_dictaminador" (tambien obligatorio).
     *
     * @return array<string, mixed>
     */
    private function payloadBase(User $medico): array
    {
        return [
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
            'puesto' => 'OPERADOR',
            'empresa' => 'CONSORCIO MINERO DEMO',
            'tipo_examen_motivo' => 'INGRESO / EVALUACIÓN INICIAL',
            'tipo_examen_categoria' => 'PERSONAL GENERAL',
            'antiguedad_empresa' => '2 AÑOS',
            'antiguedad_puesto' => '1 AÑO',
            'exposicion_poe' => 'NINGUNA',
            'medico_dictaminador' => $medico->id,
            'peso' => '82',
            'talla' => '174',
            'ta_sistolica' => '124',
            'ta_diastolica' => '82',
            'gradiente' => '2',
            'fecha_siguiente_valoracion' => 'NO APTO',
            'bh_grupo_rh' => 'O POSITIVO',
            'ad_opi' => 'NO REACTIVO',
            'ad_coc' => 'NO REACTIVO',
            'ad_thc' => 'NO REACTIVO',
            'ad_bzo' => 'NO REACTIVO',
            'ad_met' => 'NO REACTIVO',
            'ad_amp' => 'NO REACTIVO',
        ];
    }

    /**
     * Crear un expediente asigna folio, calcula edad/IMC/TA conjunta/estado
     * nutricional, normaliza la talla y registra "Creación de Folio".
     */
    public function test_crear_expediente_calcula_los_campos_derivados(): void
    {
        $medico = User::factory()->create();

        $respuesta = $this->actingAsUsuario($medico)
            ->postJson('/api/v1/expedientes', $this->payloadBase($medico));

        $respuesta->assertStatus(201)
            ->assertJsonPath('folio', 1)
            ->assertJsonPath('values.edad', 41)
            ->assertJsonPath('values.grupo_edad', '36-45')
            ->assertJsonPath('values.talla', '1.74')
            ->assertJsonPath('values.ta_conjunta', '124/82')
            ->assertJsonPath('values.estado_nutricional', 'SOBREPESO')
            ->assertJsonPath('values.fecha_siguiente_valoracion', 'NO APTO')
            ->assertJsonPath('values.gradiente', 2);

        $this->assertDatabaseHas('auditoria', [
            'anio' => now('America/Mexico_City')->year,
            'folio_anual' => 1,
            'accion' => 'Creación de Folio',
        ]);
    }

    /**
     * Un paciente recurrente (mismo CURP) reutiliza el mismo registro de
     * pacientes al crear un segundo expediente.
     */
    public function test_paciente_recurrente_reutiliza_el_mismo_registro(): void
    {
        $medico = User::factory()->create();

        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', $this->payloadBase($medico));
        $segundo = $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', array_merge(
            $this->payloadBase($medico),
            ['tipo_examen_motivo' => 'RENOVACIÓN ANUAL'],
        ));

        $segundo->assertStatus(201)->assertJsonPath('folio', 2);
        $this->assertDatabaseCount('pacientes', 1);
    }

    /**
     * Un expediente sin nombre completo ni CURP debe rechazarse.
     */
    public function test_expediente_sin_nombre_ni_curp_es_invalido(): void
    {
        $medico = User::factory()->create();
        $payload = $this->payloadBase($medico);
        unset($payload['nombre_completo'], $payload['curp']);

        $this->actingAsUsuario($medico)
            ->postJson('/api/v1/expedientes', $payload)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Abrir un expediente registra "Consulta" en la bitacora.
     */
    public function test_consultar_expediente_registra_bitacora(): void
    {
        $medico = User::factory()->create();
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', $this->payloadBase($medico));

        $this->actingAsUsuario($medico)->getJson('/api/v1/expedientes/'.$this->clave(1))->assertStatus(200);

        $this->assertDatabaseHas('auditoria', [
            'anio' => now('America/Mexico_City')->year,
            'folio_anual' => 1,
            'accion' => 'Consulta',
        ]);
    }

    /**
     * Editar un expediente sin cambiar nada registra "Guardado sin cambios";
     * si si cambia algo, el detalle incluye la etiqueta oficial del campo.
     */
    public function test_editar_expediente_registra_el_detalle_correcto(): void
    {
        $medico = User::factory()->create();
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', $this->payloadBase($medico));

        $this->actingAsUsuario($medico)->putJson('/api/v1/expedientes/'.$this->clave(1), $this->payloadBase($medico));
        $this->assertDatabaseHas('auditoria', [
            'anio' => now('America/Mexico_City')->year,
            'folio_anual' => 1,
            'accion' => 'Edición de Expediente',
            'detalle' => 'Guardado sin cambios',
        ]);

        $payloadConCambio = array_merge($this->payloadBase($medico), ['peso' => '90']);
        $this->actingAsUsuario($medico)->putJson('/api/v1/expedientes/'.$this->clave(1), $payloadConCambio);

        // No se ordena por "fecha": dentro de una misma transaccion de prueba,
        // now() de Postgres no avanza entre dos inserciones, asi que el id
        // (uuid ordenado por tiempo) es el unico orden confiable aqui.
        $this->assertSame(
            '082 Peso',
            Auditoria::where('accion', 'Edición de Expediente')->orderByDesc('id')->first()->detalle,
        );
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
