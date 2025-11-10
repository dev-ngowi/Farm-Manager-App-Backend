<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_appointments', function (Blueprint $table) {
            $table->id('appointment_id');

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            $table->foreignId('animal_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null');

            $table->foreignId('health_id')
                  ->nullable()
                  ->constrained('health_reports', 'health_id')
                  ->onDelete('set null');

            $table->enum('appointment_type', [
                'Emergency', 'Routine Checkup', 'Vaccination',
                'Surgery', 'Follow-up', 'Consultation'
            ]);

            $table->date('appointment_date');
            $table->time('appointment_time')->nullable();

            $table->enum('location_type', ['Farm Visit', 'Clinic Visit'])
                  ->default('Farm Visit');

            $table->foreignId('farm_location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->onDelete('set null');

            $table->enum('status', [
                'Scheduled', 'Confirmed', 'In Progress',
                'Completed', 'Cancelled', 'No Show'
            ])->default('Scheduled');

            $table->text('cancellation_reason')->nullable();
            $table->smallInteger('estimated_duration_minutes')->nullable();

            $table->timestamp('actual_start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();

            $table->decimal('fee_charged', 10, 2)->nullable();
            $table->enum('payment_status', ['Pending', 'Paid', 'Waived'])
                  ->default('Pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for calendar & notifications
            $table->index(['vet_id', 'appointment_date']);
            $table->index(['farmer_id', 'appointment_date']);
            $table->index('status');
            $table->index('appointment_date');
            $table->index(['appointment_date', 'appointment_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_appointments');
    }
};
