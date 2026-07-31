<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Separa el identificador público del número visible del turno.
 *
 * Hasta ahora la URL y la pantalla usaban el ID autoincremental. Eso permite
 * enumerar turnos (probar /turnos/1, /turnos/2...) y deja ver cuántos pacientes
 * atiende el centro. El ULID es opaco y no ordenable a simple vista.
 *
 * Pero el número que se le canta al paciente NO puede ser un ULID: tiene que
 * ser corto y pronunciable. Por eso aparece `daily_number`, correlativo que
 * reinicia cada día. Además es mejor que el ID: hoy el número crecía para
 * siempre y el turno 1.284 no le dice nada a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->after('id');
            $table->unsignedInteger('daily_number')->nullable()->after('ulid');
            $table->date('daily_date')->nullable()->after('daily_number');
        });

        $this->rellenarExistentes();

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('ulid');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique('ulid');
            // Índice único que respalda el bloqueo al asignar el correlativo:
            // aunque dos altas simultáneas calculen el mismo número, la base
            // rechaza la segunda en vez de emitir dos turnos con el mismo número.
            $table->unique(['daily_date', 'daily_number'], 'appointments_daily_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_daily_number_unique');
            $table->dropUnique(['ulid']);
            $table->dropColumn(['ulid', 'daily_number', 'daily_date']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }

    /** Backfill: los registros ya cargados necesitan ULID y número del día. */
    private function rellenarExistentes(): void
    {
        foreach (DB::table('patients')->whereNull('ulid')->pluck('id') as $id) {
            DB::table('patients')->where('id', $id)->update(['ulid' => (string) Str::ulid()]);
        }

        $contadorPorDia = [];

        $turnos = DB::table('appointments')
            ->whereNull('ulid')
            ->orderBy('id')
            ->get(['id', 'registered_at', 'created_at']);

        foreach ($turnos as $turno) {
            $fecha = substr((string) ($turno->registered_at ?? $turno->created_at), 0, 10);
            $contadorPorDia[$fecha] = ($contadorPorDia[$fecha] ?? 0) + 1;

            DB::table('appointments')->where('id', $turno->id)->update([
                'ulid' => (string) Str::ulid(),
                'daily_number' => $contadorPorDia[$fecha],
                'daily_date' => $fecha,
            ]);
        }
    }
};
