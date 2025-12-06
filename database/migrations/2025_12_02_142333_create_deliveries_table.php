<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // migration: create_deliveries_table.php
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            // FIX: Use standard Laravel 'id' for the primary key.
            // This column is now named 'id'.
            $table->id();

            // This constraint is now correct as 'inseminations' primary key was standardized to 'id'
            $table->foreignId('insemination_id')->unique()->constrained('inseminations')->onDelete('cascade');

            $table->date('actual_delivery_date');
            $table->enum('delivery_type', ['Normal', 'Assisted', 'C-Section', 'Dystocia']);
            $table->integer('calving_ease_score')->nullable()->comment('1 = no assistance, 5 = extreme difficulty');
            $table->integer('total_born')->default(0);
            $table->integer('live_born')->default(0);
            $table->integer('stillborn')->default(0);
            $table->enum('dam_condition_after', ['Excellent', 'Good', 'Weak', 'Critical']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
