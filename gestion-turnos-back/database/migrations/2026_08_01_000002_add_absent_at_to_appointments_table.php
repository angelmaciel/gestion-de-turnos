<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora un turno ausente solo quedaba marcado por `updated_at`, que no
 * sirve para estadísticas: cualquier otro update posterior lo pisa. Las
 * estadísticas de la Torre de Control necesitan el momento exacto en que se
 * marcó la inasistencia.
 *
 * Sin backfill de filas viejas: no hay forma confiable de reconstruir ese
 * instante para turnos ya cerrados (mismo criterio que la migración de ULIDs
 * en patients/appointments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('absent_at')->nullable()->after('attended_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('absent_at');
        });
    }
};
