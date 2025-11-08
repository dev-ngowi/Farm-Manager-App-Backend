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
        Schema::create('streets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ward_id')
                  ->constrained('wards')
                  ->onDelete('restrict')
                  ->comment('Parent ward - cannot delete ward if streets exist');

            $table->string('street_name', 100)
                  ->nullable(false)
                  ->comment('Official street or village (mtaa) name (e.g., Mwanambaya, Sokoku, Mbuyuni)');

           
            $table->timestamp('created_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamp('updated_at')
                  ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
        });

        // Indexes for fast lookups
        Schema::table('streets', function (Blueprint $table) {
            $table->index('street_name');
        });

        // Table comment (perfect for documentation)
        DB::statement("ALTER TABLE streets COMMENT = 'Streets and villages (Mitaa) within wards - lowest administrative unit in Tanzania'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streets');
    }
};