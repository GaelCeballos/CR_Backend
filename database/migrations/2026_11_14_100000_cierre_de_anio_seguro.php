<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierre de año seguro (auditoria run-1): antes el cierre descargaba y borraba
     * un año completo sin dejar rastro y sin saber si el respaldo habia llegado.
     *
     * - Bitacora: 'Respaldo de Año' (descarga) y 'Cierre de Año' (borrado). La
     *   columna es un enum de Laravel: en Postgres es un CHECK.
     * - respaldos_anio: cada respaldo que el front confirmo haber recibido completo
     *   (ya con el Excel generado). El borrado exige que el ultimo siga vigente.
     */
    public function up(): void
    {
        DB::statement('alter table auditoria drop constraint auditoria_accion_check');
        DB::statement("alter table auditoria add constraint auditoria_accion_check check (accion in ('Creación de Folio', 'Edición de Expediente', 'Consulta', 'Importación Histórica', 'Respaldo de Año', 'Cierre de Año'))");

        Schema::create('respaldos_anio', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->smallInteger('anio');
            // Cada modulo se respalda y se cierra por separado (Publico en General
            // tendra el suyo); hoy solo existe 'empresas'.
            $table->string('modulo', 20)->default('empresas');
            $table->integer('total');
            // sha256 de "id:updated_at" de cada expediente en orden de folio: si el
            // año cambia despues del respaldo, ya no coincide y no se puede borrar.
            $table->char('huella', 64);
            $table->foreignUuid('usuario_id')->references('id')->on('users');
            $table->string('usuario_nombre');
            $table->timestampTz('fecha')->useCurrent();

            $table->index(['anio', 'modulo'], 'respaldos_anio_anio_modulo_idx');
        });
    }

    /**
     * Revierte. Falla a proposito si ya hay eventos de respaldo o cierre en la
     * bitacora: es append-only y no se borran registros para poder revertir.
     */
    public function down(): void
    {
        Schema::dropIfExists('respaldos_anio');
        DB::statement('alter table auditoria drop constraint auditoria_accion_check');
        DB::statement("alter table auditoria add constraint auditoria_accion_check check (accion in ('Creación de Folio', 'Edición de Expediente', 'Consulta', 'Importación Histórica'))");
    }
};
