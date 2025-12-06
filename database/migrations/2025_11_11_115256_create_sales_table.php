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
        Schema::create('sales', function (Blueprint $table) {
            // FIX 1: Use standard $table->id() for the primary key (creates 'id' column)
            $table->id();

            $table->foreignId('farmer_id')->constrained('farmers')->onDelete('cascade');

            // FIX 2: Explicitly constrain 'animal_id' to the 'animal_id' column in 'livestock'
            $table->foreignId('animal_id')
                  ->nullable()
                  ->constrained('livestock', 'animal_id') // 👈 Corrected reference column
                  ->onDelete('set null');

            $table->string('sale_type', 20); // 'Animal', 'Milk', 'Other'
            $table->string('buyer_name');
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_location')->nullable();
            $table->decimal('quantity', 10, 2)->nullable(); // e.g., 450.00 liters or 1 animal
            $table->string('unit')->nullable(); // 'liters', 'kg', 'head'
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 14, 2);
            $table->date('sale_date');
            $table->string('payment_method', 20); // Cash, M-Pesa, Bank, Cheque
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('Completed'); // Completed, Pending, Cancelled
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['farmer_id', 'sale_date']);
            $table->index('sale_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
