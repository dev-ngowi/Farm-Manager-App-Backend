<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_treatments', function (Blueprint $table) {
            $table->id('treatment_id');

            // FIXED: Point to correct primary key 'diagnosis_id'
            $table->foreignId('diagnosis_id')
                  ->nullable()
                  ->constrained('health_diagnoses', 'diagnosis_id')
                  ->nullOnDelete();

            $table->foreignId('health_id')
                  ->constrained('health_reports', 'health_id')
                  ->cascadeOnDelete();

            $table->date('treatment_date')->useCurrent();
            $table->string('drug_name');
            $table->string('dosage');
            $table->string('route');
            $table->string('frequency');
            $table->integer('duration_days')->nullable();
            $table->string('administered_by')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->enum('outcome', ['Recovered', 'Improved', 'No Change', 'Worsened', 'Deceased', 'In Progress'])
                  ->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('treatment_date');
            $table->index('drug_name');
            $table->index('outcome');
            $table->index('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_treatments');
    }
};
