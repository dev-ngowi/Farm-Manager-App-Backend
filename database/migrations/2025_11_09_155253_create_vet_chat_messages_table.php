<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                  ->constrained('vet_chat_conversations', 'conversation_id')
                  ->onDelete('cascade');

            $table->foreignId('sender_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->text('message_text')->nullable();

            $table->string('media_url', 512)->nullable();
            $table->enum('media_type', ['Image', 'Video', 'Document', 'Audio'])
                  ->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Indexes for speed
            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_user_id');
            $table->index('is_read');
            $table->index('media_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_chat_messages');
    }
};
