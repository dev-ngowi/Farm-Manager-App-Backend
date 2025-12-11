<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id('prescription_id');

            $table->foreignId('action_id')
                  ->constrained('vet_actions', 'action_id')
                  ->onDelete('cascade');

            $table->foreignId('animal_id')
                  ->constrained('livestock', 'animal_id')
                  ->onDelete('cascade');

            $table->foreignId('vet_id')
                  ->constrained('veterinarians')
                  ->onDelete('cascade');

            $table->foreignId('farmer_id')
                  ->constrained('farmers')
                  ->onDelete('cascade');

            $table->foreignId('drug_id')
                  ->nullable()
                  ->constrained('drug_catalog')
                  ->onDelete('set null');

            $table->string('drug_name_custom')->nullable();
            $table->string('dosage');
            $table->string('frequency');
            $table->tinyInteger('duration_days')->unsigned();

            $table->enum('administration_route', ['Oral', 'Injection', 'Topical', 'IV', 'Other']);

            $table->text('special_instructions')->nullable();

            $table->date('prescribed_date');
            $table->date('start_date');
            $table->date('end_date');

            $table->tinyInteger('withdrawal_period_days')
                  ->nullable()
                  ->unsigned();

            $table->decimal('quantity_prescribed', 10, 2)->nullable();

            $table->enum('prescription_status', ['Active', 'Completed', 'Discontinued'])
                  ->default('Active');

            $table->timestamps();

            // Indexes
            $table->index(['animal_id', 'prescribed_date']);
            $table->index('prescription_status');
            $table->index('end_date');
            $table->index('vet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
