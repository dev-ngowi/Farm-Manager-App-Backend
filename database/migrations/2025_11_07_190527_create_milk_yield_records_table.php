<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_yield_records', function (Blueprint $table) {
            $table->id('yield_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade'); // Animal sold/deceased → delete milk history

            $table->date('yield_date');
            $table->enum('milking_session', ['Morning', 'Afternoon', 'Evening']);

            $table->decimal('quantity_liters', 10, 2); // e.g., 28.75 L

            $table->enum('quality_grade', ['A', 'B', 'C', 'Rejected'])
                  ->default('A');

            $table->decimal('fat_content', 4, 2)->nullable();     // e.g., 3.85%
            $table->decimal('protein_content', 4, 2)->nullable(); // e.g., 3.25%
            $table->integer('somatic_cell_count')->unsigned()->nullable(); // SCC

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Critical indexes for dairy dashboards
            $table->index('animal_id');
            $table->index('yield_date');
            $table->index('milking_session');
            $table->index('quality_grade');

            // Most used: daily total per cow
            $table->index(['animal_id', 'yield_date']);
            // Herd daily summary
            $table->index(['yield_date', 'milking_session']);
            // Mastitis detection
            $table->index('somatic_cell_count');
            // Payment grading
            $table->index(['quality_grade', 'yield_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_yield_records');
    }
};