<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_reports', function (Blueprint $table) {
            $table->id('health_id');

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade');

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->text('symptoms');
            $table->date('symptom_onset_date')->nullable();

            $table->enum('severity', ['Mild', 'Moderate', 'Severe', 'Critical'])
                  ->default('Moderate');

            $table->string('media_url', 512)->nullable();

            $table->date('report_date');

            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 10, 8)->nullable();

            $table->enum('status', [
                'Pending Diagnosis',
                'Under Diagnosis',
                'Awaiting Treatment',
                'Under Treatment',
                'Recovered',
                'Deceased'
            ])->default('Pending Diagnosis');

            $table->enum('priority', ['Low', 'Medium', 'High', 'Emergency'])
                  ->default('Medium');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for fast filtering
            $table->index('status');
            $table->index('priority');
            $table->index('report_date');
            $table->index(['farmer_id', 'status']);
            $table->index(['animal_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_reports');
    }
};
