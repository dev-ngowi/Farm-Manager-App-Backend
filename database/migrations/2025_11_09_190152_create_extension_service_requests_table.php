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
        Schema::create('extension_service_requests', function (Blueprint $table) {
    $table->id('request_id');
    $table->foreignId('farmer_id')->constrained('farmers')->onDelete('cascade');
    $table->string('service_type'); // Training, Soil Test, Market Linkage, etc.
    $table->text('description');
    $table->date('preferred_date')->nullable();
    $table->enum('status', ['Pending', 'Assigned', 'Completed', 'Cancelled'])->default('Pending');
    $table->text('officer_notes')->nullable();
    $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->onDelete('set null');
    $table->timestamps();

    $table->index('farmer_id');
    $table->index('status');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extension_service_requests');
    }
};
