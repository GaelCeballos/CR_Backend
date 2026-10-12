<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitacora de accesos y cambios (spec §3.5, §4.6). Solo la escribe el servidor,
     * dentro de la misma transaccion del evento que la dispara.
     */
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->timestampTz('fecha')->useCurrent();
            $table->foreignUuid('usuario_id')->references('id')->on('users');
            $table->string('usuario_nombre');
            $table->enum('accion', ['Creación de Folio', 'Edición de Expediente', 'Consulta']);
            $table->foreignUuid('historia_id')->nullable()->references('id')->on('historias_clinicas');
            $table->bigInteger('folio');
            $table->string('paciente')->nullable();
            $table->text('detalle');

            $table->index('fecha', 'auditoria_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
