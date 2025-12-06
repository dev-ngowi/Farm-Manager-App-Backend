<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_schedules', function (Blueprint $table) {
            $table->id('schedule_id');

            // --- PLANNING & PRESCRIPTION (Set by Vet) ---
            $table->foreignId('animal_id')
                  ->constrained('livestock')
                  ->onDelete('cascade');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            // Link back to the historical action that created this future plan (optional)
            $table->foreignId('vet_action_id')
                  ->nullable()
                  ->constrained('vet_actions', 'action_id')
                  ->onDelete('set null');

            $table->string('vaccine_name');
            $table->string('disease_prevented');
            $table->date('scheduled_date');

            // Dose details (optional, if provided by Vet)
            $table->string('batch_number')->nullable();
            $table->text('notes')->nullable();

            // --- EXECUTION & STATUS (Set by Farmer) ---
            $table->enum('status', [
                'Pending', 'Completed', 'Missed', 'Rescheduled'
            ])->default('Pending');

            // The actual date/time the dose was given
            $table->date('completed_date')->nullable();

            // Tracks which user (Farmer/Manager) marked it as completed
            $table->foreignId('administered_by_user_id')
                  ->nullable()
                  ->constrained('users') // Assuming a 'users' table
                  ->onDelete('set null');

            $table->boolean('reminder_sent')->default(false);

            $table->timestamps();

            // Indexes for lookup and dashboard efficiency
            $table->index(['animal_id', 'scheduled_date']);
            $table->index(['vet_id', 'status']);
            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_schedules');
    }
};
