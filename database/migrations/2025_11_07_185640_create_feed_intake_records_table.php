<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_intake_records', function (Blueprint $table) {
            $table->id('intake_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade'); // If animal sold/deceased → delete feed history

            $table->foreignId('feed_id')
                  ->constrained('feed_inventory', 'feed_id')
                  ->onDelete('cascade'); // If feed type deleted → clean up records

            $table->date('intake_date');
            $table->decimal('quantity', 10, 2); // e.g., 5.75 Kg

            $table->enum('feeding_time', ['Morning', 'Afternoon', 'Evening', 'Night'])
                  ->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // updated_at not needed unless editing past records

            // Indexes for blazing-fast queries
            $table->index('animal_id');
            $table->index('feed_id');
            $table->index('intake_date');
            $table->index('feeding_time');

            // Most common queries: daily total per animal
            $table->index(['animal_id', 'intake_date']);
            $table->index(['feed_id', 'intake_date']);

            // Monthly reports
            $table->index(['intake_date', 'animal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_intake_records');
    }
};