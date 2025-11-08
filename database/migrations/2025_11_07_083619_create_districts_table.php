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
        Schema::create('districts', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('region_id')
                  ->constrained('regions')
                  ->onDelete('restrict')
                  ->comment('Reference to regions table');

            $table->string('district_name', 100)
                  ->nullable(false)
                  ->comment('Name of the district (e.g., Ilala, Arusha Urban)');

            $table->string('district_code', 10)
                  ->unique()
                  ->nullable()
                  ->comment('Official district code (e.g., 0201, 1103)');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Optional: Add index for better query performance
        Schema::table('districts', function (Blueprint $table) {
            $table->index('district_name');
        });

        // Optional: Table comment
        DB::statement('ALTER TABLE districts COMMENT = "Districts within regions (Tanzania & Zanzibar)"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};