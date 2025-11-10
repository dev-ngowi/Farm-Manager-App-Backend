<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('drug_name')->unique();
            $table->string('generic_name')->nullable();
            $table->string('drug_category', 100)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('common_dosage')->nullable();
            $table->tinyInteger('withdrawal_period_days')
                  ->nullable()
                  ->unsigned()
                  ->comment('Days to wait before slaughter/milk use');
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('storage_conditions')->nullable();
            $table->boolean('is_prescription_only')->default(true);
            $table->timestamps();

            // Regular indexes
            $table->index('drug_name');
            $table->index('generic_name');
            $table->index('drug_category');
            $table->index('is_prescription_only');

            // FIX: Give FULLTEXT index a short name (max 64 chars)
            $table->fullText(['drug_name', 'generic_name', 'drug_category', 'manufacturer'], 'drug_search_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_catalog');
    }
};
