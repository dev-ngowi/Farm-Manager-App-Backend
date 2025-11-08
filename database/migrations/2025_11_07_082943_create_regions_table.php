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
        Schema::create('regions', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->string('region_name', 100)
                  ->unique()
                  ->nullable(false)
                  ->comment('Region name (e.g., Dar es Salaam, Zanzibar)');

            $table->string('region_code', 10)
                  ->unique()
                  ->nullable()
                  ->comment('Official region code (e.g., TZ-01, ZNZ)');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'))
                  ->comment('Creation date');

            // Optional: If you want updated_at as well (recommended)
            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Optional: Add table comment
        DB::statement('ALTER TABLE regions COMMENT = "Top-level geographic regions of Tanzania and Zanzibar"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};