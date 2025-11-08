<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestock', function (Blueprint $table) {
            $table->id('animal_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('restrict');

            $table->foreignId('species_id')
                  ->constrained('species')
                  ->onDelete('restrict');

            $table->foreignId('breed_id')
                  ->constrained('breeds')
                  ->onDelete('restrict');

            $table->string('tag_number', 100)->unique();
            $table->string('name', 100)->nullable();
            $table->enum('sex', ['Male', 'Female']);
            $table->date('date_of_birth')->nullable();
            $table->decimal('weight_at_birth_kg', 10, 2)->nullable();
            $table->decimal('current_weight_kg', 10, 2)->nullable();

            // Self-referencing: Parents (can be null)
            $table->foreignId('sire_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null');

            $table->foreignId('dam_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null');

            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('source', 255)->nullable();

            $table->enum('status', ['Active', 'Sold', 'Deceased', 'Transferred'])
                  ->default('Active');

            $table->date('disposal_date')->nullable();
            $table->text('disposal_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            // Indexes for performance
            $table->index('farmer_id');
            $table->index('species_id');
            $table->index('breed_id');
            $table->index('tag_number');
            $table->index('status');
            $table->index('sex');
            $table->index('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livestock');
    }
};