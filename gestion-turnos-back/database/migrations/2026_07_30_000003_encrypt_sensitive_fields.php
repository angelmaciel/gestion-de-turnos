<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cifra en reposo los datos personales y clínicos.
 *
 * Los valores cifrados son cadenas largas y de longitud variable, así que las
 * columnas pasan a TEXT. Además, el cifrado de Laravel usa un IV aleatorio:
 * el mismo texto produce cifrados distintos cada vez. Eso significa que
 * NO se puede buscar ni poner un índice único sobre la columna cifrada.
 *
 * Por eso la cédula suma `cedula_hash`: un HMAC-SHA256 determinista del valor
 * normalizado ("índice ciego"). Permite buscar y garantizar unicidad sin
 * guardar la cédula en claro, porque el HMAC no es reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // La unicidad se traslada al índice ciego: el cifrado no es determinista.
            $table->dropUnique(['cedula']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->text('cedula')->change();
            $table->string('cedula_hash', 64)->nullable()->after('cedula');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('cedula_hash');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->text('weight')->nullable()->change();
            $table->text('height')->nullable()->change();
            $table->text('blood_pressure')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['cedula_hash']);
            $table->dropColumn('cedula_hash');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('cedula')->change();
            $table->unique('cedula');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->change();
            $table->decimal('height', 5, 2)->nullable()->change();
            $table->string('blood_pressure')->nullable()->change();
        });
    }
};
