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
        Schema::create('wards', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('district_id')
                  ->constrained('districts')
                  ->onDelete('restrict')
                  ->comment('Parent district - cannot delete district if wards exist');

            $table->string('ward_name', 100)
                  ->nullable(false)
                  ->comment('Official ward name (e.g., Kivule, Mburufu, Magomeni)');

            $table->string('ward_code', 10)
                  ->unique()
                  ->nullable()
                  ->comment('Official ward code (e.g., 020101, 110301)');

            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'))
                  ->comment('When ward record was added');

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Performance indexes
        Schema::table('wards', function (Blueprint $table) {
            $table->index('ward_name');
            $table->index('ward_code');
        });

        // Table comment
        DB::statement('ALTER TABLE wards COMMENT = "Administrative wards within districts (Tanzania & Zanzibar)"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};