<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista de solo lectura para el selector de medicos del formulario de expedientes.
 * Las vistas no se crean con el Schema Builder; esto es normal incluso en
 * migraciones Laravel estandar.
 */
return new class extends Migration
{
    /**
     * Crea la vista medicos_selector: medicos activos con cedula y universidad
     * capturadas, listos para mostrarse en el selector del formulario.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create view medicos_selector as
            select id, nombre_completo, cedula_profesional, universidad,
                   nombre_completo || ' - Cédula: ' || cedula_profesional || ' (' || universidad || ')' as etiqueta
            from users
            where estado = 'activo'
              and nombre_completo is not null and cedula_profesional is not null and universidad is not null
        SQL);
    }

    /**
     * Elimina la vista medicos_selector.
     */
    public function down(): void
    {
        DB::statement('drop view if exists medicos_selector');
    }
};
