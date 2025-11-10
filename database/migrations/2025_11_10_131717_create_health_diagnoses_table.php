<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_diagnoses', function (Blueprint $table) {
            $table->id('diagnosis_id');
            $table->foreignId('health_id')->constrained('health_reports', 'health_id')->cascadeOnDelete();
            $table->foreignId('vet_id')->nullable()->constrained('veterinarians')->nullOnDelete();
            $table->date('diagnosis_date')->useCurrent();
            $table->string('disease_condition');
            $table->enum('diagnosis_method', ['Clinical', 'Lab Test', 'Ultrasound', 'X-Ray', 'Postmortem'])->default('Clinical');
            $table->enum('confidence_level', ['High', 'Medium', 'Low'])->default('Medium');
            $table->json('lab_results')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->timestamps();

            $table->index(['disease_condition']);
            $table->index(['diagnosis_date']);
            $table->index(['vet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_diagnoses');
    }
};
