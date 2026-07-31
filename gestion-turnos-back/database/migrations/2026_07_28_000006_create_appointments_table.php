<?php

use App\Enums\AppointmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained('professionals')->nullOnDelete();
            $table->string('status')->default(AppointmentStatus::REGISTRADO->value);
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_called_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('preconsulta_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
