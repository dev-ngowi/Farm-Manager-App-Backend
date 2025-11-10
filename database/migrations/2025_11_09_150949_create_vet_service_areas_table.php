<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_service_areas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

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

            $table->decimal('service_radius_km', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Unique: One vet can't have duplicate area assignments
            $table->unique(['vet_id', 'region_id', 'district_id', 'ward_id'], 'unique_vet_service_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_service_areas');
    }
};
