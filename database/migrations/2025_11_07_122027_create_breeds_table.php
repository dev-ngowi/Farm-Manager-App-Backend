<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('species_id');
            $table->foreign('species_id')
            ->references('id')
            ->on('species')
            ->onUpdate('cascade')
            ->onDelete('restrict');

            $table->string('breed_name', 100);
            $table->string('origin', 100)->nullable();

            $table->enum('purpose', ['Meat', 'Milk', 'Wool', 'Dual-purpose', 'Other'])
                  ->default('Other');

            $table->decimal('average_weight_kg', 8, 2)->nullable();
            $table->tinyInteger('maturity_months')->unsigned()->nullable();

            $table->timestamps();

            // Indexes
            $table->index('species_id');
            $table->index('breed_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
    }
};
