<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "En limpieza" es un estado manual: no hay forma de derivarlo de los turnos,
 * así que necesita su propio campo en vez de calcularse como el resto de los
 * estados de la Torre de Control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('en_limpieza')->default(false)->after('professional_id');
            $table->timestamp('en_limpieza_desde')->nullable()->after('en_limpieza');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['en_limpieza', 'en_limpieza_desde']);
        });
    }
};
