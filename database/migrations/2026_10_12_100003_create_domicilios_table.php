<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instantanea del domicilio vigente al momento del examen (spec §2.1, §2.3).
     * No lleva columna de codigo postal: el CP solo es un asistente de captura
     * (ver /api/v1/inegi/cp/{cp}) y nunca se persiste junto al domicilio.
     */
    public function up(): void
    {
        Schema::create('domicilios', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->foreignUuid('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $table->string('calle')->nullable();
            $table->string('colonia')->nullable();
            $table->string('municipio')->nullable();
            $table->string('estado')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domicilios');
    }
};
