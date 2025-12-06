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
        Schema::create('offspring', function (Blueprint $table) {
            // FIX 1: Use standard Laravel 'id' primary key
            $table->id();

            // FIX 2: This now correctly references the standard 'id' in the 'deliveries' table.
            $table->foreignId('delivery_id')->constrained('deliveries')->onDelete('cascade');

            $table->string('temporary_tag')->nullable();
            $table->string('gender');
            $table->decimal('birth_weight_kg', 5, 2);
            $table->enum('birth_condition', ['Vigorous', 'Weak', 'Stillborn']);
            $table->enum('colostrum_intake', ['Adequate', 'Partial', 'Insufficient', 'None']);
            $table->boolean('navel_treated')->default(false);

            // Constraint referencing 'animal_id' in 'livestock' (as it hasn't been standardized yet)
            $table->foreignId('livestock_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offspring');
    }
};
