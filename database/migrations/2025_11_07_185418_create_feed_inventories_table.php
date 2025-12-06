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
        Schema::create('feed_inventory', function (Blueprint $table) {
            // FIX: Use standard Laravel 'id' primary key for easy referencing.
            $table->id();

            $table->string('feed_name', 255);
            $table->enum('feed_type', ['Concentrate', 'Roughage', 'Silage', 'Minerals', 'Other']);

            $table->enum('unit', ['Kg', 'Liters', 'Bundles', 'Bags'])
                  ->default('Kg');

            $table->decimal('protein_percentage', 5, 2)->nullable(); // e.g., 18.50%
            $table->decimal('energy_content', 8, 2)->nullable(); // MJ/kg
            $table->decimal('cost_per_unit', 10, 2)->nullable();     // e.g., 450.00

            $table->string('supplier', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            // Unique constraint: prevent duplicate feed names with same type/unit
            $table->unique(['feed_name', 'feed_type', 'unit']);

            // Indexes for fast filtering
            $table->index('feed_type');
            $table->index('unit');
            $table->index('cost_per_unit');
            $table->index('protein_percentage');
            $table->index('energy_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_inventory');
    }
};
