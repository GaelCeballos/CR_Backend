<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega 'Cierre de Año' a las acciones de la bitacora. El cierre descarga y
     * borra el historial completo de un año y antes no dejaba rastro (auditoria
     * run-1). La columna es un enum de Laravel: en Postgres es un CHECK.
     */
    public function up(): void
    {
        DB::statement('alter table auditoria drop constraint auditoria_accion_check');
        DB::statement("alter table auditoria add constraint auditoria_accion_check check (accion in ('Creación de Folio', 'Edición de Expediente', 'Consulta', 'Importación Histórica', 'Cierre de Año'))");
    }

    /**
     * Quita la accion. Falla a proposito si ya hay eventos de cierre: la
     * bitacora es append-only y no se borran registros para poder revertir.
     */
    public function down(): void
    {
        DB::statement('alter table auditoria drop constraint auditoria_accion_check');
        DB::statement("alter table auditoria add constraint auditoria_accion_check check (accion in ('Creación de Folio', 'Edición de Expediente', 'Consulta', 'Importación Histórica'))");
    }
};
