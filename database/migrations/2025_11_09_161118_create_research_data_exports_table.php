<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_data_exports', function (Blueprint $table) {
            $table->id('export_id');

            $table->foreignId('request_id')
                  ->constrained('research_data_requests', 'request_id')
                  ->onDelete('cascade');

            $table->foreignId('researcher_id')
                  ->constrained('researchers')
                  ->onDelete('cascade');

            $table->enum('export_format', ['CSV', 'Excel', 'JSON', 'PDF', 'SQL']);

            $table->string('file_name');
            $table->integer('file_size_kb')->nullable();
            $table->string('file_path', 512)->nullable();
            $table->string('download_url', 512)->nullable();

            $table->integer('record_count')->nullable();
            $table->boolean('anonymization_applied')->default(true);

            $table->date('export_date');
            $table->smallInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['researcher_id', 'export_date']);
            $table->index('expires_at');
            $table->index('download_count');
            $table->index(['request_id', 'export_format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_data_exports');
    }
};
