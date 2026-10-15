<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la casilla "Exámenes visuales realizados" (flujo Fundación SABI)
     * a hc_exploracion. Nullable: ninguna historia existente queda marcada
     * ni sin marcar a la fuerza.
     */
    public function up(): void
    {
        Schema::table('hc_exploracion', function (Blueprint $table) {
            $table->boolean('examenes_visuales_realizados')->nullable()->after('lentes_seguridad');
        });
    }

    /**
     * Quita la columna examenes_visuales_realizados.
     */
    public function down(): void
    {
        Schema::table('hc_exploracion', function (Blueprint $table) {
            $table->dropColumn('examenes_visuales_realizados');
        });
    }
};
