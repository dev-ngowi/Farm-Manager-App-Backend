<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_actions', function (Blueprint $table) {
            $table->id('action_id'); // 1. Using 'action_id' as primary key

            $table->foreignId('health_id')
                  ->constrained('health_reports', 'health_id')
                  ->onDelete('cascade');

            $table->foreignId('diagnosis_id')
                  ->nullable() // Allowing null if action is taken without formal diagnosis
                  ->constrained('diagnosis_responses')
                  ->onDelete('set null');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            $table->enum('action_type', [
                'Treatment', 'Advisory', 'Vaccination',
                'Prescription', 'Surgery', 'Consultation'
            ]);

            $table->date('action_date')->useCurrent(); // Defaulting to current date
            $table->time('action_time')->nullable();

            $table->enum('action_location', [
                'Farm Visit', 'Clinic', 'Remote Consultation'
            ])->default('Remote Consultation');

            // Treatment fields
            $table->string('medicine_name')->nullable();
            $table->string('dosage')->nullable();
            $table->enum('administration_route', ['Oral', 'Injection', 'Topical', 'IV', 'Other'])
                  ->nullable();

            // Advisory
            $table->text('advice_notes')->nullable();

            // Vaccination (Dose administered TODAY)
            $table->string('vaccine_name')->nullable();
            $table->string('vaccine_batch_number')->nullable();
            $table->date('vaccination_date')->nullable();
            // Removed: $table->date('next_vaccination_due')->nullable();

            // Prescription / Surgery
            $table->text('prescription_details')->nullable();
            $table->text('surgery_details')->nullable();

            // Cost & Payment
            $table->decimal('treatment_cost', 10, 2)->nullable();
            $table->enum('payment_status', ['Pending', 'Paid', 'Waived'])
                  ->default('Pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['vet_id', 'action_date']);
            $table->index('action_type');
            $table->index('payment_status');
            $table->index('action_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_actions');
    }
};
