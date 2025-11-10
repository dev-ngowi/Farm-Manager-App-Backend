<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_id')
                  ->constrained('health_reports', 'health_id')
                  ->onDelete('cascade');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            $table->string('suspected_disease');
            $table->text('diagnosis_notes')->nullable();
            $table->text('recommended_tests')->nullable();

            $table->enum('prognosis', ['Excellent', 'Good', 'Fair', 'Poor', 'Grave'])
                  ->nullable();

            $table->tinyInteger('estimated_recovery_days')
                  ->nullable()
                  ->unsigned();

            $table->date('diagnosis_date');
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('diagnosis_date');
            $table->index(['health_id', 'vet_id']);
            $table->index('prognosis');
            $table->unique('health_id', 'unique_one_diagnosis_per_report'); // One diagnosis per report
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_responses');
    }
};
