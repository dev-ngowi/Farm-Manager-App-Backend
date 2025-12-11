<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_records', function (Blueprint $table) {
            $table->id('recovery_id');

            $table->foreignId('action_id')
      ->constrained('vet_actions', 'action_id')
      ->onDelete('cascade');

            $table->enum('recovery_status', [
                'Ongoing', 'Improved', 'Fully Recovered',
                'No Change', 'Worsened', 'Deceased'
            ])->default('Ongoing');

            $table->date('recovery_date')->nullable();
            $table->tinyInteger('recovery_percentage')
                  ->nullable()
                  ->unsigned()
                  ->comment('0 to 100');

            $table->text('recovery_notes')->nullable();

            $table->enum('reported_by', ['Farmer', 'Veterinarian'])
                  ->default('Farmer');

            $table->timestamps();

            // Indexes
            $table->index('recovery_status');
            $table->index('recovery_date');
            $table->index('reported_by');
            $table->index(['action_id', 'recovery_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_records');
    }
};
