<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audiometria (182-199): oido x frecuencia, normalizada en filas.
     */
    public function up(): void
    {
        Schema::create('hc_audiometria', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->references('id')->on('historias_clinicas')->cascadeOnDelete();
            $table->enum('oido', ['OD', 'OI']);
            $table->smallInteger('frecuencia_hz');
            $table->rawColumn('umbral_db', 'numeric');

            $table->primary(['historia_id', 'oido', 'frecuencia_hz']);
        });

        DB::statement('ALTER TABLE hc_audiometria ADD CONSTRAINT frecuencia_valida CHECK (frecuencia_hz IN (150, 250, 500, 1000, 2000, 3000, 4000, 6000, 8000))');
        DB::statement('ALTER TABLE hc_audiometria ADD CONSTRAINT umbral_rango CHECK (umbral_db BETWEEN -10 AND 120)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hc_audiometria');
    }
};
