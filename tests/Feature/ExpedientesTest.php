<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\User;
use App\Services\FolioAnualService;
use App\Services\PacienteUpsertService;
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
     * Si dos equipos guardan al mismo tiempo y el folio choca, el alta se
     * repite con el siguiente folio libre en vez de responder un error.
     */
    public function test_folio_duplicado_por_alta_simultanea_se_reintenta(): void
    {
        $medico = User::factory()->create();
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', $this->payloadBase($medico))->assertStatus(201);
        $existente = HistoriaClinica::firstOrFail();

        $folios = app(FolioAnualService::class);
        $intentos = 0;

        $historia = $folios->conReintento(function () use ($folios, $existente, $medico, &$intentos) {
            $intentos++;

            return HistoriaClinica::create([
                'paciente_id' => $existente->paciente_id,
                // Primer intento: simula que otro equipo ya se quedo con este folio.
                'folio_anual' => $intentos === 1 ? $existente->folio_anual : $folios->siguiente($existente->anio),
                'creado_por' => $medico->id,
                'actualizado_por' => $medico->id,
            ]);
        });

        $this->assertSame(2, $intentos);
        $this->assertSame(2, (int) $historia->folio_anual);
    }

    /**
     * Si dos equipos dan de alta al mismo paciente nuevo al mismo tiempo y el
     * CURP choca, el alta se repite y reutiliza al paciente ya creado.
     */
    public function test_paciente_duplicado_por_alta_simultanea_se_reintenta(): void
    {
        $medico = User::factory()->create();
        $this->actingAsUsuario($medico)->postJson('/api/v1/expedientes', $this->payloadBase($medico))->assertStatus(201);
        $existente = Paciente::firstOrFail();

        $intentos = 0;

        $paciente = app(FolioAnualService::class)->conReintento(function () use ($existente, &$intentos) {
            $intentos++;

            // Primer intento: simula que el otro equipo creo al paciente justo antes.
            return $intentos === 1
                ? Paciente::create(['nombre_completo' => 'OTRO EQUIPO', 'curp' => $existente->curp])
                : app(PacienteUpsertService::class)->upsert(['nombre_completo' => 'OTRO EQUIPO', 'curp' => $existente->curp]);
        });

        $this->assertSame(2, $intentos);
        $this->assertSame($existente->id, $paciente->id);
        $this->assertSame(1, Paciente::count());
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
