<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla hc_laboratorio. Los valores son texto libre: el medico puede
     * escribir "95 mg/dL", "NEGATIVO", etc.
     */
    public function up(): void
    {
        Schema::create('hc_laboratorio', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->primary()
                ->references('id')->on('historias_clinicas')->cascadeOnDelete();

            // Quimica sanguinea 134-145
            $table->string('qs_glucosa')->nullable();
            $table->string('qs_urea')->nullable();
            $table->string('qs_creatinina')->nullable();
            $table->string('qs_acido_urico')->nullable();
            $table->string('qs_colesterol')->nullable();
            $table->string('qs_trigliceridos')->nullable();
            $table->string('qs_albumina')->nullable();
            $table->string('qs_bilirrubina_total')->nullable();
            $table->string('qs_ast')->nullable();
            $table->string('qs_fosfatasa_alcalina')->nullable();
            $table->string('qs_dhl')->nullable();
            $table->string('qs_hba1c')->nullable();

            // Biometria hematica 146-159
            $table->enum('bh_grupo_rh', [
                'O POSITIVO', 'O NEGATIVO', 'A POSITIVO', 'A NEGATIVO',
                'B POSITIVO', 'B NEGATIVO', 'AB POSITIVO', 'AB NEGATIVO',
            ])->nullable();
            $table->string('bh_leucocitos')->nullable();
            $table->string('bh_eritrocitos')->nullable();
            $table->string('bh_hemoglobina')->nullable();
            $table->string('bh_hematocrito')->nullable();
            $table->string('bh_vcm')->nullable();
            $table->string('bh_mch')->nullable();
            $table->string('bh_mchc')->nullable();
            $table->string('bh_plaquetas')->nullable();
            $table->string('bh_mpv')->nullable();
            $table->string('bh_neutrofilos')->nullable();
            $table->string('bh_linfocitos_pct')->nullable();
            $table->string('bh_monocitos')->nullable();
            $table->string('bh_linfocitos_abs')->nullable();

            // Antidoping 160-165
            foreach (['ad_opi', 'ad_coc', 'ad_thc', 'ad_bzo', 'ad_met', 'ad_amp'] as $columna) {
                $table->enum($columna, ['NO REACTIVO', 'REACTIVO', 'NO REALIZADO'])->nullable();
            }

            // Examen general de orina 166-179
            $table->string('ego_color')->nullable();
            $table->string('ego_aspecto')->nullable();
            $table->string('ego_densidad')->nullable();
            $table->string('ego_ph')->nullable();
            $table->string('ego_nitritos')->nullable();
            $table->string('ego_proteinas')->nullable();
            $table->string('ego_glucosa')->nullable();
            $table->string('ego_cetonas')->nullable();
            $table->string('ego_bilirrubina')->nullable();
            $table->string('ego_urobilinogeno')->nullable();
            $table->string('ego_hemoglobina')->nullable();
            $table->string('ego_leucocitos')->nullable();
            $table->string('ego_eritrocitos')->nullable();
            $table->string('ego_cristales')->nullable();
        });
    }

    /**
     * Elimina la tabla hc_laboratorio.
     */
    public function down(): void
    {
        Schema::dropIfExists('hc_laboratorio');
    }
};
