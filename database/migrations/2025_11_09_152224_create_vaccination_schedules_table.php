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

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade');

            $table->string('vaccine_name');
            $table->string('disease_prevented');

            $table->date('scheduled_date');

            $table->enum('status', ['Pending', 'Completed', 'Missed', 'Rescheduled'])
                  ->default('Pending');

            $table->boolean('reminder_sent')->default(false);

            $table->foreignId('vet_id')
                  ->nullable()
                  ->constrained('veterinarians')
                  ->onDelete('set null');

            $table->date('completed_date')->nullable();

            $table->foreignId('action_id')
                  ->nullable()
                  ->constrained('vet_actions')
                  ->onDelete('set null');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('scheduled_date');
            $table->index('status');
            $table->index('reminder_sent');
            $table->index(['animal_id', 'scheduled_date']);
            $table->index(['vet_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_schedules');
    }
};
