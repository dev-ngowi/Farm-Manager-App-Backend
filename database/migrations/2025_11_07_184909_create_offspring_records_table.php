<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offspring_records', function (Blueprint $table) {
            $table->id('offspring_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('birth_id')
                  ->constrained('birth_records')
                  ->onDelete('cascade'); // If birth deleted → all offspring records gone

            $table->string('animal_tag', 100)->unique();
            $table->enum('gender', ['Male', 'Female']);
            $table->decimal('weight_at_birth_kg', 5, 2)->nullable();

            $table->enum('health_status', ['Healthy', 'Weak', 'Deceased'])
                  ->default('Healthy');

            $table->enum('colostrum_intake', ['Adequate', 'Insufficient', 'None'])
                  ->nullable();

            $table->boolean('registered_as_livestock')->default(false);
            
            $table->foreignId('livestock_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null');

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('birth_id');
            $table->index('animal_tag');
            $table->index('livestock_id');
            $table->index('health_status');
            $table->index('colostrum_intake');
            $table->index('registered_as_livestock');

            // Fast lookup: unregistered healthy calves
            $table->index(['registered_as_livestock', 'health_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offspring_records');
    }
};