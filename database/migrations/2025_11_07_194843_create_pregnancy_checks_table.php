<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pregnancy_checks', function (Blueprint $table) {
            $table->id('check_id'); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            $table->foreignId('breeding_id')
                  ->constrained('breeding_records')
                  ->onDelete('cascade'); // Delete checks if breeding deleted

            $table->foreignId('vet_id')
                  ->nullable()
                  ->constrained('veterinarians')
                  ->onDelete('set null'); // Vet deleted → keep record

            $table->date('check_date');
            $table->enum('method', ['Ultrasound', 'Palpation', 'Blood Test', 'Visual']);
            $table->enum('result', ['Pregnant', 'Not Pregnant', 'Unknown']);

            $table->date('expected_delivery_date')->nullable();
            $table->unsignedTinyInteger('fetus_count')->nullable(); // 1–4 typical

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index('breeding_id');
            $table->index('vet_id');
            $table->index('check_date');
            $table->index('method');
            $table->index('result');
            $table->index('expected_delivery_date');

            // Common reports
            $table->index(['breeding_id', 'check_date']);
            $table->index(['result', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pregnancy_checks');
    }
};