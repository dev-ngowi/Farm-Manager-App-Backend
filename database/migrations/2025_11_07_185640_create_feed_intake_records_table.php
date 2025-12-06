<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feed_intake_records', function (Blueprint $table) {
            // FIX 1: Use standard $table->id() to create the primary key 'id'
            $table->id();

            // FIX 2: Correcting the 'feed_id' reference.
            // It must reference the primary key column ('id') in the 'feed_inventory' table.
            $table->foreignId('feed_id')
                  ->constrained('feed_inventory') // Removed the explicit 'feed_id' column reference
                  ->onDelete('cascade');

            // Assuming 'livestock' table has an 'animal_id' primary key. If it uses 'id',
            // this constraint will also need correction (see note below).
            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade');

            $table->date('intake_date');
            $table->decimal('quantity', 10, 2); // e.g., 5.75 Kg

            $table->enum('feeding_time', ['Morning', 'Afternoon', 'Evening', 'Night'])
                  ->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); // Added updated_at for completeness

            // Indexes
            $table->index('animal_id');
            $table->index('feed_id');
            $table->index('intake_date');
            $table->index('feeding_time');
            $table->index(['animal_id', 'intake_date']);
            $table->index(['feed_id', 'intake_date']);
            $table->index(['intake_date', 'animal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_intake_records');
    }
};
