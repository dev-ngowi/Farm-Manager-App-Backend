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
        Schema::create('birth_records', function (Blueprint $table) {
            // 1. Primary Key
            $table->id(); // Correctly defines the primary key 'id'.
            // Removed the custom name $table->id('id') as it is redundant.

            // 2. Foreign Keys
            $table->foreignId('breeding_id')
                  ->constrained('breeding_records')
                  ->onDelete('cascade'); // If breeding deleted → birth record gone

            // FIX: Renamed the foreign key column from 'id' to 'veterinarian_id'
            // to avoid the duplicate column name error (1060).
            $table->foreignId('veterinarian_id')
                  ->nullable()
                  ->constrained('veterinarians')
                  ->onDelete('set null');

            // 3. Data Fields
            $table->date('birth_date');
            $table->time('birth_time')->nullable();

            $table->tinyInteger('total_offspring')->unsigned();
            $table->tinyInteger('live_births')->unsigned()->default(0);
            $table->tinyInteger('stillbirths')->unsigned()->default(0);

            $table->enum('birth_type', ['Natural', 'Assisted', 'Cesarean'])
                  ->default('Natural');

            $table->text('complications')->nullable();

            $table->enum('dam_condition', ['Good', 'Fair', 'Poor', 'Critical'])
                  ->default('Good');

            $table->text('notes')->nullable();

            // 4. Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); // Added standard updated_at for completeness

            // 5. Indexes (Corrected to use 'veterinarian_id' for indexing)
            $table->index('breeding_id');
            $table->index('birth_date');
            $table->index('veterinarian_id'); // Updated index name
            $table->index('dam_condition');
            $table->index('birth_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birth_records');
    }
};
