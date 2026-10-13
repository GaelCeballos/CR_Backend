<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla domicilios. No lleva columna de codigo postal: el CP solo
     * se usa en el frontend para ayudar a escribir el domicilio, nunca se guarda.
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

    /**
     * Elimina la tabla domicilios.
     */
    public function down(): void
    {
        Schema::dropIfExists('domicilios');
    }
};
