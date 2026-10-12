<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * El folio es una columna identity de Postgres, y las secuencias no son
 * transaccionales: RefreshDatabase revierte las filas pero no el contador.
 * Este trait lo reinicia antes de cada prueba para que folio siempre
 * empiece en 1 y las aserciones sean legibles.
 */
trait ReseteaSecuenciaFolio
{
    /**
     * Corre el setUp normal y despues reinicia la secuencia del folio.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('ALTER SEQUENCE historias_clinicas_folio_seq RESTART WITH 1');
    }
}
