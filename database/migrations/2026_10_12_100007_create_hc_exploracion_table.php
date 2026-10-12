<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla hc_exploracion. ta_conjunta, imc y estado_nutricional son
     * columnas generadas (stored) que Postgres calcula solo a partir de los
     * signos vitales y peso/talla capturados.
     */
    public function up(): void
    {
        Schema::create('hc_exploracion', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->primary()
                ->references('id')->on('historias_clinicas')->cascadeOnDelete();

            // Signos vitales 075-081 — numeric sin escala fija (igual que el spec), para que
            // ta_conjunta imprima "124/82" y no "124.00/82.00".
            $table->rawColumn('pulso', 'numeric')->nullable();
            $table->rawColumn('respiracion', 'numeric')->nullable();
            $table->rawColumn('temperatura', 'numeric')->nullable();
            $table->rawColumn('ta_sistolica', 'numeric')->nullable();
            $table->rawColumn('ta_diastolica', 'numeric')->nullable();
            $table->text('ta_conjunta')->nullable()->storedAs(
                "case when ta_sistolica is not null and ta_diastolica is not null " .
                "then ta_sistolica::text || '/' || ta_diastolica::text end"
            );
            $table->string('clasificacion_aha')->nullable();

            // Aspecto general 082-087 — tambien numeric sin escala fija, como en el spec.
            $table->rawColumn('peso', 'numeric')->nullable();
            $table->rawColumn('talla', 'numeric')->nullable();
            $table->decimal('imc', 6, 2)->nullable()->storedAs(
                'case when peso is not null and talla is not null then round(peso / (talla * talla), 2) end'
            );
            $table->string('estado_nutricional')->nullable()->storedAs(<<<'SQL'
                case
                  when peso is null or talla is null then null
                  when round(peso / (talla * talla), 2) < 18.5 then 'BAJO PESO'
                  when round(peso / (talla * talla), 2) < 25   then 'PESO NORMAL'
                  when round(peso / (talla * talla), 2) < 30   then 'SOBREPESO'
                  when round(peso / (talla * talla), 2) < 35   then 'OBESIDAD I'
                  when round(peso / (talla * talla), 2) < 40   then 'OBESIDAD II'
                  else 'OBESIDAD III'
                end
                SQL
            );
            $table->string('constitucion')->nullable();
            $table->string('piel_faneras')->nullable();

            // Torax y abdomen 088-095
            $table->text('torax_obs')->nullable();
            $table->text('torax_pulmones')->nullable();
            $table->text('torax_corazon')->nullable();
            $table->text('abdomen_obs')->nullable();
            $table->text('abdomen_higado')->nullable();
            $table->text('abdomen_hernias')->nullable();
            $table->text('abdomen_hemorroides')->nullable();
            $table->text('abdomen_bazo')->nullable();

            // Ojos 096-113
            $table->string('vl_od')->nullable();
            $table->string('vl_oi')->nullable();
            $table->string('vl_con_lentes')->nullable();
            $table->string('vl_resultado')->nullable();
            $table->string('vc_od')->nullable();
            $table->string('vc_oi')->nullable();
            $table->string('vc_con_lentes')->nullable();
            $table->string('vc_resultado')->nullable();
            $table->string('reflejos_oculares_od')->nullable();
            $table->string('reflejos_oculares_oi')->nullable();
            $table->string('pterigion_od')->nullable();
            $table->string('pterigion_oi')->nullable();
            $table->string('catarata_od')->nullable();
            $table->string('catarata_oi')->nullable();
            $table->string('estrabismo')->nullable();
            $table->string('ishihara')->nullable();
            $table->text('oftal_recomendaciones')->nullable();
            $table->enum('lentes_seguridad', [
                'PX NO NECESITA LENTES GRADUADOS DE SEGURIDAD',
                'PX REQUIERE LENTES GRADUADOS DE SEGURIDAD',
            ])->nullable();

            // Oidos 114-117
            $table->string('canal_auditivo_od')->nullable();
            $table->string('canal_auditivo_oi')->nullable();
            $table->string('membrana_timpanica_d')->nullable();
            $table->string('membrana_timpanica_i')->nullable();

            // Nariz, orofaringe y cuello 118-122
            $table->text('nariz_obs')->nullable();
            $table->text('nariz_obstrucciones')->nullable();
            $table->text('orofaringe')->nullable();
            $table->string('dentadura')->nullable();
            $table->text('cuello')->nullable();

            // Genitourinario 123-126
            $table->text('gu_obs')->nullable();
            $table->string('gu_hernias')->nullable();
            $table->string('gu_hidrocele')->nullable();
            $table->string('gu_varicocele')->nullable();

            // Extremidades y neurologico 127-131
            $table->text('ext_sup_obs')->nullable();
            $table->string('ext_sup_manos')->nullable();
            $table->text('ext_inf_obs')->nullable();
            $table->string('ext_inf_hallux_valgus')->nullable();
            $table->string('reflejos_osteotendinosos')->nullable();

            // Psiquismo 132-133
            $table->text('psiquismo')->nullable();
            $table->text('padecimiento_actual')->nullable();
        });

        DB::statement('ALTER TABLE hc_exploracion ADD CONSTRAINT peso_rango CHECK (peso IS NULL OR (peso > 0 AND peso <= 400))');
        DB::statement('ALTER TABLE hc_exploracion ADD CONSTRAINT talla_rango CHECK (talla IS NULL OR talla BETWEEN 0.3 AND 3)');
    }

    /**
     * Elimina la tabla hc_exploracion.
     */
    public function down(): void
    {
        Schema::dropIfExists('hc_exploracion');
    }
};
