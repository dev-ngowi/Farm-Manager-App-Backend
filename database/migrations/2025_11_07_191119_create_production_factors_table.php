<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_factors', function (Blueprint $table) {
            $table->id('factor_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade');

            $table->date('calculation_date');     // When this was computed
            $table->date('period_start');         // e.g., 2025-10-01
            $table->date('period_end');           // e.g., 2025-10-31

            $table->decimal('total_feed_consumed_kg', 10, 2)->nullable();
            $table->decimal('total_milk_produced_liters', 10, 2)->nullable();
            $table->decimal('weight_gain_kg', 8, 2)->nullable();

            // Efficiency KPIs
            $table->decimal('feed_to_milk_ratio', 5, 2)->nullable();     // kg feed per liter milk
            $table->decimal('feed_conversion_ratio', 5, 2)->nullable(); // kg feed per kg gain (FCR)
            $table->decimal('milk_per_kg_feed', 5, 2)->nullable();      // liters per kg feed

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for dashboards
            $table->index('animal_id');
            $table->index('calculation_date');
            $table->index(['period_start', 'period_end']);
            $table->index('feed_to_milk_ratio');
            $table->index('feed_conversion_ratio');
            $table->index('milk_per_kg_feed');

            // Prevent duplicate calculations for same animal + period
            $table->unique(['animal_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_factors');
    }
};