<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->foreignId('farmer_id')->constrained('farmers')->onDelete('cascade');
            $table->foreignId('animal_id')->nullable()->constrained('livestock', 'animal_id')->onDelete('set null');
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->enum('status', ['Pending', 'Assigned', 'Completed', 'Cancelled', 'Failed'])
                  ->default('Pending');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_vet_id')->nullable()->constrained('veterinarians')->onDelete('set null');
            $table->date('assigned_date')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('farmer_id');
            $table->index('status');
            $table->index('preferred_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
