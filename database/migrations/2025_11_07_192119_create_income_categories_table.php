<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_categories', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->string('category_name', 100)->unique();
            $table->text('description')->nullable();

            $table->boolean('is_animal_specific')
                  ->default(false)
                  ->comment('Can this income be linked to a specific animal? (e.g., Milk Sale, Animal Sale)');

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('category_name');
            $table->index('is_animal_specific');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_categories');
    }
};