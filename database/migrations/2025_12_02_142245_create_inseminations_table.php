// In: database/migrations/2025_12_02_142245_create_inseminations_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inseminations', function (Blueprint $table) {
            // FIX 1: Standardize primary key to 'id'
            $table->id();

            // References 'animal_id' in livestock (assuming livestock hasn't been fixed yet)
            $table->foreignId('dam_id')->constrained('livestock', 'animal_id')->onDelete('cascade');
            $table->foreignId('sire_id')->nullable()->constrained('livestock', 'animal_id');

            // FIX 2: Constrain to the new standard 'id' primary key in 'semen_inventory'.
            // Removed the explicit 'semen_id' reference column.
            $table->foreignId('semen_id')
                  ->nullable()
                  ->constrained('semen_inventory');

            $table->foreignId('heat_cycle_id')->nullable()->constrained('heat_cycles')->onDelete('set null');
            $table->foreignId('technician_id')->nullable()->constrained('users');
            $table->enum('breeding_method', ['Natural', 'AI']);
            $table->date('insemination_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['Pending', 'Confirmed Pregnant', 'Not Pregnant', 'Delivered', 'Failed'])->default('Pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['dam_id', 'insemination_date']);
            $table->unique(['dam_id', 'insemination_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inseminations');
    }
};
