<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // migration: create_lactations_table.php
public function up()
{
    Schema::create('lactations', function (Blueprint $table) {
        $table->id('lactation_id');
        $table->foreignId('dam_id')->constrained('livestock', 'animal_id')->onDelete('cascade');
        $table->integer('lactation_number');
        $table->date('start_date'); // calving date
        $table->date('peak_date')->nullable();
        $table->date('dry_off_date')->nullable();
        $table->integer('total_milk_kg')->default(0);
        $table->integer('days_in_milk')->nullable();
        $table->enum('status', ['Ongoing', 'Completed'])->default('Ongoing');
        $table->timestamps();

        $table->unique(['dam_id', 'lactation_number']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lactations');
    }
};
