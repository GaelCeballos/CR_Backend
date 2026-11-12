<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la llave con la que el front cifra el borrador de un expediente
     * nuevo que no alcanzo a enviarse (se fue el internet o se cerro la sesion).
     * Se guarda cifrada con APP_KEY (cast "encrypted"): un respaldo de la base
     * por si solo no permite leer los borradores. Nullable: se genera en el
     * siguiente login de cada cuenta.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('llave_borrador')->nullable();
        });
    }

    /**
     * Quita la columna llave_borrador.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('llave_borrador');
        });
    }
};
