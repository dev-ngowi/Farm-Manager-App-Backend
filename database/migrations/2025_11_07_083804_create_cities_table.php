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
        Schema::create('cities', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('region_id')
                  ->constrained('regions')
                  ->onDelete('restrict')
                  ->comment('Parent region');

            $table->foreignId('district_id')
                  ->nullable()
                  ->constrained('districts')
                  ->onDelete('set null')
                  ->comment('Parent district (nullable for city-regions like Dar es Salaam)');

            $table->string('city_name', 100)
                  ->nullable(false)
                  ->comment('City / Town name (e.g., Dar es Salaam, Arusha, Mwanza, Stone Town)');

            $table->string('city_code', 10)
                  ->unique()
                  ->nullable()
                  ->comment('Official city code (e.g., DAR, ARU, ZNZ)');

            $table->boolean('is_capital')->default(false)
                  ->comment('Is this a regional or national capital?');

            $table->boolean('is_active')->default(true)
                  ->comment('Soft status flag');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Indexes for performance
        Schema::table('cities', function (Blueprint $table) {
            $table->index('city_name');
            $table->index('city_code');
            $table->index('is_capital');
            $table->index(['region_id', 'district_id']);
        });

        // Table comment
        DB::statement('ALTER TABLE cities COMMENT = "Cities and major towns in Tanzania & Zanzibar (with optional district link)"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};