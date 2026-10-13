<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla hc_antecedentes: heredofamiliares, no patologicos, habitos y
     * patologicos de una historia clinica (relacion 1 a 1 con historias_clinicas).
     */
    public function up(): void
    {
        Schema::create('hc_antecedentes', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->primary()
                ->references('id')->on('historias_clinicas')->cascadeOnDelete();

            // Heredofamiliares 027-038
            $table->text('ahf_diabetes')->nullable();
            $table->text('ahf_hipertension')->nullable();
            $table->text('ahf_cardiopatia')->nullable();
            $table->text('ahf_neoplasias')->nullable();
            $table->text('ahf_infecciosa')->nullable();
            $table->text('ahf_otras')->nullable();
            $table->text('ahf_abuelos_paternos')->nullable();
            $table->text('ahf_abuelos_maternos')->nullable();
            $table->text('ahf_padre')->nullable();
            $table->text('ahf_madre')->nullable();
            $table->text('ahf_hermanos')->nullable();
            $table->text('ahf_hijos')->nullable();

            // No patologicos 039-045
            $table->enum('escolaridad', [
                'Sin Escolaridad', 'Primaria Incompleta', 'Primaria Terminada',
                'Secundaria Incompleta', 'Secundaria Terminada', 'Preparatoria Incompleta',
                'Preparatoria Terminada', 'Licenciatura Incompleta', 'Licenciatura Terminada',
                'Maestría / Postgrado',
            ])->nullable();
            $table->text('vivienda')->nullable();
            $table->text('alimentacion')->nullable();
            $table->text('convivencia_animales')->nullable();
            $table->text('actividad_fisica')->nullable();
            $table->text('inmunizaciones')->nullable();
            $table->text('tatuajes')->nullable();

            // Enfermedades profesionales 058
            $table->text('enfermedades_profesionales')->nullable();

            // Habitos 059-065
            $table->text('etilismo')->nullable();
            $table->text('tabaquismo')->nullable();
            $table->text('adicciones')->nullable();
            $table->text('alergias')->nullable();
            $table->text('quirurgicos')->nullable();
            $table->text('traumatismos')->nullable();
            $table->text('hemotransfusiones')->nullable();

            // Patologicos 066-074
            $table->text('app_hta')->nullable();
            $table->text('app_dm')->nullable();
            $table->text('app_cardiaca')->nullable();
            $table->text('app_respiratoria')->nullable();
            $table->text('app_renal')->nullable();
            $table->text('app_musculo_esqueletica')->nullable();
            $table->text('app_lumbar')->nullable();
            $table->text('app_acido_peptica')->nullable();
            $table->text('app_otras')->nullable();
        });
    }

    /**
     * Elimina la tabla hc_antecedentes.
     */
    public function down(): void
    {
        Schema::dropIfExists('hc_antecedentes');
    }
};
