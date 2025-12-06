<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feed_stock', function (Blueprint $table) {
            $table->id();

            // These foreign keys are now correctly formed because
            // 'farmers' and 'feed_inventory' are assumed to have 'id' primary keys.
            $table->foreignId('farmer_id')->constrained('farmers')->onDelete('cascade');
            $table->foreignId('feed_id')->constrained('feed_inventory')->onDelete('restrict');

            $table->string('batch_number', 50)->unique(); // e.g., BATCH-2025-001
            $table->date('purchase_date');
            $table->decimal('quantity_purchased_kg', 10, 2);
            $table->decimal('remaining_kg', 10, 2);
            $table->decimal('purchase_price_per_kg', 10, 2); // TZS per kg

            // Correction: Removed 'storedAs' for simplicity, leaving it as a regular decimal field.
            $table->decimal('total_cost', 12, 2);

            $table->date('expiry_date')->nullable();
            $table->string('supplier_name', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['farmer_id', 'feed_id']);
            $table->index('remaining_kg');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_stock');
    }
};
