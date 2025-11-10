<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_chat_conversations', function (Blueprint $table) {
            $table->id('conversation_id');

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            $table->foreignId('health_id')
                  ->nullable()
                  ->constrained('health_reports', 'health_id')
                  ->onDelete('set null');

            $table->string('subject')->nullable();

            $table->enum('status', ['Active', 'Resolved', 'Closed'])
                  ->default('Active');

            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])
                  ->default('Medium');

            $table->timestamps();

            // Unique: one active conversation per farmer-vet-health combo
            $table->unique(['farmer_id', 'vet_id', 'health_id'], 'unique_farmer_vet_health');

            // Indexes
            $table->index(['vet_id', 'status']);
            $table->index(['farmer_id', 'status']);
            $table->index('priority');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_chat_conversations');
    }
};
