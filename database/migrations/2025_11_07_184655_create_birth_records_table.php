<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birth_records', function (Blueprint $table) {
            $table->id('id'); 

            $table->foreignId('breeding_id')
                  ->constrained('breeding_records')
                  ->onDelete('cascade'); // If breeding deleted → birth record gone

            $table->date('birth_date');
            $table->time('birth_time')->nullable();

            $table->tinyInteger('total_offspring')->unsigned();
            $table->tinyInteger('live_births')->unsigned()->default(0);
            $table->tinyInteger('stillbirths')->unsigned()->default(0);

            $table->enum('birth_type', ['Natural', 'Assisted', 'Cesarean'])
                  ->default('Natural');

            $table->text('complications')->nullable();

            $table->enum('dam_condition', ['Good', 'Fair', 'Poor', 'Critical'])
                  ->default('Good');

            $table->foreignId('vet_id')
                  ->nullable()
                  ->constrained('veterinarians')
                  ->onDelete('set null');

            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // updated_at not needed unless you allow editing birth records

            // Indexes
            $table->index('breeding_id');
            $table->index('birth_date');
            $table->index('vet_id');
            $table->index('dam_condition');
            $table->index('birth_type');

            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birth_records');
    }
};