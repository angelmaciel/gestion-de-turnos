<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de auditoría sobre datos de pacientes.
 *
 * En sistemas de salud es exigible poder responder "quién accedió o modificó
 * la información de este paciente y cuándo". Sin esto, una filtración interna
 * es indetectable e imposible de atribuir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nunca en cascada: si se borra el usuario, el registro debe sobrevivir.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_email')->nullable();   // Copia congelada al momento del hecho.
            $table->string('user_roles')->nullable();

            $table->string('accion', 60);               // consulto_cola, completo_preconsulta, llamo_paciente...
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('contexto')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['patient_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
