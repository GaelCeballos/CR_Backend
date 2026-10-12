<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gabinete (180-181, 200-203).
     */
    public function up(): void
    {
        Schema::create('hc_gabinete', function (Blueprint $table) {
            $table->foreignUuid('historia_id')->primary()
                ->references('id')->on('historias_clinicas')->cascadeOnDelete();

            $table->text('rx_torax')->nullable();
            $table->text('rx_lumbar')->nullable();
            $table->text('audiometria_resultado')->nullable();
            $table->text('ecg')->nullable();
            $table->text('espirometria')->nullable();
            $table->text('valoracion_oftalmologica')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hc_gabinete');
    }
};
