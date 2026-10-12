<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista para el selector de medicos (spec §2.4). Las vistas no se crean con el
 * Schema Builder; esto es normal incluso en migraciones Laravel estándar.
 */
return new class extends Migration
{
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

    public function down(): void
    {
        DB::statement('drop view if exists medicos_selector');
    }
};
