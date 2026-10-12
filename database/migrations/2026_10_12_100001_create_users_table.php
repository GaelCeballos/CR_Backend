<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'));
            $table->primary('id');
            $table->string('email')->unique();
            $table->string('hashed_password');
            $table->string('nombre_completo');
            $table->string('cedula_profesional')->nullable();
            $table->string('universidad')->nullable();
            $table->enum('rol', ['admin', 'servicios_medicos'])->default('servicios_medicos');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->boolean('is_temporary_password')->default(true);
            $table->timestampTz('ultimo_acceso')->nullable();
            $table->foreignUuid('creado_por')->nullable()->references('id')->on('users');
            $table->timestampsTz();
        });

        // Reglas de §5.1/§5.6 que el Schema Builder no puede expresar como columnas.
        DB::statement("ALTER TABLE users ADD CONSTRAINT nombre_completo_formato CHECK (nombre_completo ~ '^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ[:space:].]+\$' AND char_length(btrim(nombre_completo)) > 0)");
        DB::statement("ALTER TABLE users ADD CONSTRAINT cedula_formato CHECK (cedula_profesional IS NULL OR cedula_profesional ~ '^[A-Za-z0-9]{5,}\$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT medico_completo CHECK (rol <> 'servicios_medicos' OR (cedula_profesional IS NOT NULL AND char_length(btrim(coalesce(universidad, ''))) >= 2))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
