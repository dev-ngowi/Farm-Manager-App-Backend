<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('researchers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('affiliated_institution');
            $table->string('department')->nullable();

            $table->enum('research_purpose', [
                'Academic',
                'Commercial Research',
                'Field Research',
                'Government Policy',
                'NGO Project'
            ]);

            $table->string('research_focus_area')->nullable();
            $table->string('academic_title')->nullable();
            $table->string('orcid_id', 50)->nullable();

            $table->boolean('is_approved')->default(false);
            $table->date('approval_date')->nullable();

            $table->foreignId('approved_by_admin_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index('is_approved');
            $table->index('research_purpose');
            $table->index('affiliated_institution');
            $table->index('orcid_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('researchers');
    }
};
