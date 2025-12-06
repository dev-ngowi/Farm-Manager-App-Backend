<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table) {
            $table->id('id')
                  ->comment('Species ID (INT as requested)');
            $table->string('species_name', 100)
                  ->unique()
                  ->nullable(false)
                  ->comment('e.g., Cattle, Goat, Sheep');

            $table->text('description')->nullable()
                  ->comment('Biological info, common uses in Tanzania, etc.');

            $table->timestamps();
        });

        // Indexes
        Schema::table('species', function (Blueprint $table) {
            $table->index('species_name');
        });

        // Table comment
        DB::statement("ALTER TABLE species COMMENT = 'Master catalog of animal species (Cattle, Goats, Sheep, Poultry, etc.) used in Tanzanian farms'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('species');
    }
};
