<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->string('nombre_completo')->nullable();
            $table->string('curp', 18)->nullable();
            $table->string('nss', 11)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['MASCULINO', 'FEMENINO'])->nullable();
            $table->string('originario')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE pacientes ADD CONSTRAINT curp_formato CHECK (curp IS NULL OR curp ~ '^[A-ZÑ0-9]{18}\$')");
        DB::statement("ALTER TABLE pacientes ADD CONSTRAINT nss_formato CHECK (nss IS NULL OR nss ~ '^[0-9]{11}\$')");

        // Un paciente por CURP y por NSS (cuando existen) — Blueprint no soporta indices unicos parciales.
        DB::statement('CREATE UNIQUE INDEX pacientes_curp_uq ON pacientes (curp) WHERE curp IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX pacientes_nss_uq ON pacientes (nss) WHERE nss IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
