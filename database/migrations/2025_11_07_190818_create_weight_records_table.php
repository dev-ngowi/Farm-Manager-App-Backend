<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id('weight_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade'); // Animal gone → delete weight history

            $table->date('record_date');
            $table->decimal('weight_kg', 10, 2); // e.g., 485.50 kg

            $table->decimal('body_condition_score', 2, 1)->nullable(); // 1.0 to 5.0
            $table->enum('measurement_method', ['Scale', 'Tape', 'Visual Estimate'])
                  ->default('Scale');

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for growth reports
            $table->index('animal_id');
            $table->index('record_date');
            $table->index('weight_kg');
            $table->index('body_condition_score');

            // Most used: growth curve per animal
            $table->index(['animal_id', 'record_date']);
            // Herd weighing sessions
            $table->index(['record_date', 'animal_id']);
            // Sale readiness
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('weight_records');
    }
};