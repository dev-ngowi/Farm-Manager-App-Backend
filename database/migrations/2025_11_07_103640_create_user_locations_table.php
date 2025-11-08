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
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('Reference to user');

            $table->foreignId('location_id')
                  ->constrained('locations')
                  ->onDelete('cascade')
                  ->comment('Reference to full address + GPS');

            $table->boolean('is_primary')
                  ->default(true)
                  ->comment('Is this the main operational location?');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Critical indexes for performance
        Schema::table('user_locations', function (Blueprint $table) {
            $table->index(['user_id', 'is_primary']);
            $table->index('location_id');
            $table->unique(['user_id', 'location_id']); // Prevent duplicates
        });

        // Table comment
        DB::statement("ALTER TABLE user_locations COMMENT = 'Many-to-many: Users ↔ Locations (with primary flag)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};