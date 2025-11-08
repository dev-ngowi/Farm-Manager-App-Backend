<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('region_id')
                  ->nullable()
                  ->constrained('regions')
                  ->onDelete('set null');

            $table->foreignId('district_id')
                  ->nullable()
                  ->constrained('districts')
                  ->onDelete('set null');

            $table->foreignId('ward_id')
                  ->nullable()
                  ->constrained('wards')
                  ->onDelete('set null');

            $table->foreignId('street_id')
                  ->nullable()
                  ->constrained('streets')
                  ->onDelete('set null');

            // Use plain decimal (safe for all Laravel versions)
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('latitude', 11, 8)->nullable();

            $table->text('address_details')->nullable();
            $table->timestamps();
        });

        // Add composite index for filtering
        Schema::table('locations', function (Blueprint $table) {
            $table->index(['region_id', 'district_id', 'ward_id', 'street_id']);
        });

       
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};