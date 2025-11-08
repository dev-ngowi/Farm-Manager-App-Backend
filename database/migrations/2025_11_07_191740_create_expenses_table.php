<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id(); 

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->foreignId('category_id')
                  ->constrained('expense_categories')
                  ->onDelete('restrict'); // Prevent deleting category if used

            $table->foreignId('animal_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('set null'); // If animal sold, keep expense history

            $table->decimal('amount', 12, 2); // Supports up to 99 million KES

            $table->date('expense_date');

            $table->enum('payment_method', ['Cash', 'Mobile Money', 'Bank Transfer', 'Credit'])
                  ->nullable();

            $table->string('vendor_supplier', 255)->nullable();
            $table->string('receipt_number', 100)->nullable();

            $table->text('description')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            // Indexes for speed & reports
            $table->index('farmer_id');
            $table->index('category_id');
            $table->index('animal_id');
            $table->index('expense_date');
            $table->index('payment_method');
            $table->index('vendor_supplier');

            // Monthly expense reports
            $table->index(['farmer_id', 'expense_date']);
            // Animal-specific costs
            $table->index(['animal_id', 'expense_date']);
            // Vendor payments
            $table->index(['vendor_supplier', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};