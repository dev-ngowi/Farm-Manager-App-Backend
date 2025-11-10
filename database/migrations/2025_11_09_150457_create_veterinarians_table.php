<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinarians', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('qualification_certificate');
            $table->string('license_number', 100)->unique();
            $table->string('specialization')->nullable();
            $table->tinyInteger('years_experience')->nullable()->unsigned();
            $table->string('clinic_name')->nullable();

            $table->foreignId('location_id')
                  ->nullable()
                  ->constrained('locations')
                  ->onDelete('set null');

            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->boolean('is_approved')->default(false);
            $table->date('approval_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinarians');
    }
};
