<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_data_requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->foreignId('researcher_id')
                  ->constrained('researchers')
                  ->onDelete('cascade');

            $table->foreignId('category_id')
                  ->constrained('data_request_categories', 'category_id')
                  ->onDelete('restrict');

            $table->string('request_title');
            $table->text('research_purpose');
            $table->text('data_usage_description');

            $table->boolean('publication_intent')->default(false);
            $table->date('expected_publication_date')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('ethics_approval_certificate')->nullable();

            $table->date('requested_date_range_start')->nullable();
            $table->date('requested_date_range_end')->nullable();

            $table->json('requested_regions')->nullable();
            $table->json('requested_species')->nullable();
            $table->json('requested_data_fields')->nullable();

            $table->enum('anonymization_level', ['Full', 'Partial', 'None'])
                  ->default('Full');

            $table->enum('status', [
                'Pending', 'Under Review', 'Approved', 'Rejected',
                'Data Prepared', 'Completed', 'Withdrawn'
            ])->default('Pending');

            $table->enum('priority', ['Low', 'Medium', 'High'])
                  ->default('Medium');

            $table->foreignId('reviewed_by_admin_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->date('review_date')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->date('data_access_granted_date')->nullable();
            $table->date('data_access_expires_date')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('priority');
            $table->index('researcher_id');
            $table->index('category_id');
            $table->index('data_access_expires_date');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_data_requests');
    }
};
