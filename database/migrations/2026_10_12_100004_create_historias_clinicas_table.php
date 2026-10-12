<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historias_clinicas', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();

            // 001 Folio: consecutivo, asignado por la BD (columna identity de Postgres).
            $table->bigInteger('folio')->generatedAs()->always()->unique();

            // 002 Fecha de realizacion: fecha del dia de creacion en zona Mexico, de solo lectura.
            $table->date('fecha_estudios')->default(DB::raw("(now() at time zone 'America/Mexico_City')::date"));

            $table->foreignUuid('paciente_id')->references('id')->on('pacientes');
            $table->foreignUuid('domicilio_id')->nullable()->references('id')->on('domicilios');
            $table->foreignUuid('medico_id')->nullable()->references('id')->on('users');

            // Identificacion laboral de la visita (004-010, 014-015)
            $table->string('puesto')->nullable();
            $table->string('empresa')->nullable();
            $table->string('zona_trabajo')->nullable();
            $table->enum('tipo_examen_motivo', [
                'INGRESO / EVALUACIÓN INICIAL', 'RENOVACIÓN ANUAL', 'CONTROL PERIÓDICO / SEGUIMIENTO',
            ])->nullable();
            $table->string('antiguedad_empresa')->nullable();
            $table->string('antiguedad_puesto')->nullable();
            $table->string('exposicion_poe')->nullable();
            $table->string('area')->nullable();
            $table->enum('tipo_examen_categoria', [
                'PERSONAL GENERAL', 'OPERADORES', 'CAT B', 'CAT C', 'VISITA TEC',
            ])->nullable();

            // Datos personales que cambian entre visitas (017-018, 023, 025-026)
            $table->smallInteger('edad')->nullable();
            $table->enum('grupo_edad', ['< 26', '26-35', '36-45', '46-50', '> 50'])->nullable();
            $table->string('telefono')->nullable();
            $table->enum('religion', ['Católica', 'Cristiana', 'Ninguna', 'Atea', 'Negado', 'Otra'])->nullable();
            $table->enum('estado_civil', [
                'SOLTERO', 'CASADO', 'UNION LIBRE', 'DIVORCIADO', 'SEPARADO', 'VIUDO',
            ])->nullable();

            // Dictamen (204-205)
            $table->text('diagnostico')->nullable();
            $table->smallInteger('gradiente')->nullable();

            // Complementarios de los formatos impresos (R1, R2, R4)
            $table->date('fecha_entrega_resultados')->nullable();
            $table->date('fecha_siguiente_valoracion')->nullable();
            $table->boolean('no_apto')->default(false);
            $table->string('tipo_empleado')->default('CONTRATISTA');

            $table->foreignUuid('creado_por')->references('id')->on('users');
            $table->foreignUuid('actualizado_por')->references('id')->on('users');
            $table->timestampsTz();

            $table->index('fecha_estudios', 'hc_fecha_idx');
            $table->index(['paciente_id', 'updated_at'], 'hc_paciente_idx');
            $table->index('tipo_examen_motivo', 'hc_motivo_idx');
        });

        DB::statement('ALTER TABLE historias_clinicas ADD CONSTRAINT edad_rango CHECK (edad IS NULL OR edad BETWEEN 0 AND 120)');
        DB::statement('ALTER TABLE historias_clinicas ADD CONSTRAINT gradiente_rango CHECK (gradiente IS NULL OR gradiente BETWEEN 1 AND 4)');
        DB::statement("ALTER TABLE historias_clinicas ADD CONSTRAINT tipo_empleado_fijo CHECK (tipo_empleado = 'CONTRATISTA')");
        DB::statement('ALTER TABLE historias_clinicas ADD CONSTRAINT r2_fecha_o_no_apto CHECK (NOT (no_apto AND fecha_siguiente_valoracion IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('historias_clinicas');
    }
};
