<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->string('category_name', 100)->unique();
            $table->text('description')->nullable();

            $table->boolean('is_animal_specific')
                  ->default(false)
                  ->comment('Can this expense be linked to a specific animal? (e.g., Vet, AI, Medicine)');

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('category_name');
            $table->index('is_animal_specific');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};