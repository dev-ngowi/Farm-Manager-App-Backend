<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeding_records', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('dam_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade'); // If animal deleted, remove breeding record

            $table->foreignId('sire_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null'); // AI or unknown sire → allow null

            $table->enum('breeding_type', ['Natural', 'AI', 'ET'])
                  ->default('Natural');

            $table->string('ai_semen_code', 100)->nullable();
            $table->string('ai_bull_name', 255)->nullable();

            $table->date('breeding_date');
            $table->date('expected_delivery_date')->nullable();

            $table->enum('status', ['Pending', 'Confirmed Pregnant', 'Failed', 'Completed'])
                  ->default('Pending');

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            // Indexes for fast queries
            $table->index('dam_id');
            $table->index('sire_id');
            $table->index('breeding_date');
            $table->index('expected_delivery_date');
            $table->index('status');
            $table->index('breeding_type');

            // Composite index for common reports
            $table->index(['dam_id', 'breeding_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeding_records');
    }
};