<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('heat_cycles', function (Blueprint $table) {
            // FIX: Use standard Laravel 'id' for the primary key.
            // This resolves the error when 'inseminations' references it.
            $table->id();

            // Retaining the explicit constraint for 'livestock' as it uses 'animal_id'
            $table->foreignId('dam_id')->constrained('livestock', 'animal_id')->onDelete('cascade');

            $table->date('observed_date');
            $table->enum('intensity', ['Weak', 'Moderate', 'Strong', 'Standing Heat']);
            $table->text('notes')->nullable();
            $table->date('next_expected_date')->nullable(); // AI-predicted next heat
            $table->boolean('inseminated')->default(false);
            $table->timestamps();

            $table->index(['dam_id', 'observed_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heat_cycles');
    }
};
