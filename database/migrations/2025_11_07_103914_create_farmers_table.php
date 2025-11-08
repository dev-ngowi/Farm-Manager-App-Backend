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
        Schema::create('farmers', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('One-to-one link to users table');

            $table->string('farm_name', 255)->nullable()
                  ->comment('e.g., Shamba la Mkulima John, Kijiji Farm');

            $table->enum('farm_purpose', ['Dairy', 'Meat', 'Dual Purpose', 'Other'])
                  ->default('Other')
                  ->comment('Primary cattle farming purpose');

            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->onDelete('set null')
                  ->comment('Farm GPS + full TZ address (region → street)');

            $table->decimal('total_land_acres', 10, 2)->nullable()
                  ->comment('Total farm size in acres (e.g., 12.50)');

            $table->unsignedTinyInteger('years_experience')->nullable()
                  ->comment('Years in cattle farming (0-100)');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Indexes for performance
        Schema::table('farmers', function (Blueprint $table) {
            $table->index('farm_purpose');
            $table->index('total_land_acres');
            $table->index('years_experience');
            $table->index('location_id');
        });

        // Table comment
        DB::statement("ALTER TABLE farmers COMMENT = 'One-to-one farmer profiles with farm details, location, and experience'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};