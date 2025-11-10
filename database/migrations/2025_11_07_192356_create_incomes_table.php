<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->foreignId('category_id')
                  ->constrained('income_categories')
                  ->onDelete('restrict'); // Can't delete category if used

            $table->foreignId('animal_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null'); // Animal sold → keep sale record

            $table->decimal('amount', 12, 2); // Up to 99 million KES
            $table->date('income_date');

            $table->enum('payment_method', ['Cash', 'Mobile Money', 'Bank Transfer', 'Check'])
                  ->nullable();

            $table->string('buyer_customer', 255)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->string('source_reference', 255)->nullable(); // e.g., "KCC - Eldoret"

            $table->text('description')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            // Indexes for performance & reports
            $table->index('farmer_id');
            $table->index('category_id');
            $table->index('animal_id');
            $table->index('income_date');
            $table->index('payment_method');
            $table->index('buyer_customer');
            $table->index('source_reference');

            // Critical reports
            $table->index(['farmer_id', 'income_date']);
            $table->index(['animal_id', 'income_date']);
            $table->index(['category_id', 'income_date']);
            $table->index(['buyer_customer', 'income_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
