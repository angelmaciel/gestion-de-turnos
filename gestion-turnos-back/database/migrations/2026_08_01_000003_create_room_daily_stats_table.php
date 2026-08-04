<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de historial para las fases futuras (predicción de retrasos, ranking
 * de especialidades): una fila por sala y día, con sumas y conteos en vez de
 * promedios ya calculados, para que esas fases puedan recalcular la fórmula
 * exacta que necesiten sin haber perdido precisión.
 *
 * specialty_id queda desnormalizado a propósito: si una sala cambia de
 * profesional/especialidad más adelante, el historial ya guardado no debe
 * reinterpretarse con la asignación nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->date('date');

            $table->unsignedInteger('atendidos_count')->default(0);
            $table->unsignedInteger('ausentes_count')->default(0);
            $table->unsignedInteger('llamados_count')->default(0);
            $table->unsignedInteger('reintentos_count')->default(0);

            $table->unsignedBigInteger('tiempo_espera_total_segundos')->default(0);
            $table->unsignedInteger('tiempo_espera_muestras')->default(0);
            $table->unsignedBigInteger('duracion_consulta_total_segundos')->default(0);
            $table->unsignedInteger('duracion_consulta_muestras')->default(0);

            $table->timestamp('primer_llamado_at')->nullable();
            $table->timestamp('ultimo_atendido_at')->nullable();
            $table->timestamp('generado_at')->nullable();

            $table->timestamps();

            $table->unique(['room_id', 'date'], 'room_daily_stats_room_date_unique');
            $table->index(['specialty_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_daily_stats');
    }
};
