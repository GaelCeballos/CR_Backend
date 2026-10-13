<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla hc_historia_laboral: hasta 3 trabajos previos por historia
     * clinica (uno por cada valor de "orden": 1 actual, 2 penultimo, 3 antepenultimo).
     */
    public function up(): void
    {
        Schema::create('hc_historia_laboral', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->references('id')->on('historias_clinicas')->cascadeOnDelete();
            $table->smallInteger('orden');
            $table->string('empresa')->nullable();
            $table->string('puesto')->nullable();
            $table->string('tiempo')->nullable();
            $table->string('condiciones')->nullable();

            $table->primary(['historia_id', 'orden']);
        });

        DB::statement('ALTER TABLE hc_historia_laboral ADD CONSTRAINT orden_valido CHECK (orden IN (1, 2, 3))');
    }

    /**
     * Elimina la tabla hc_historia_laboral.
     */
    public function down(): void
    {
        Schema::dropIfExists('hc_historia_laboral');
    }
};
