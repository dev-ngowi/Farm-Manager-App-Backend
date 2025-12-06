// In: database/migrations/XXXX_XX_XX_XXXXXX_create_semen_inventory_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('semen_inventory', function (Blueprint $table) {
            // FIX: Standardize primary key to 'id'
            $table->id();
            $table->unsignedBigInteger('farmer_id'); // ⭐ ADDED: To link semen to a farmer

            $table->string('straw_code')->unique();

            // Assuming 'livestock' still uses 'animal_id' as PK for now
            $table->foreignId('bull_id')->nullable()->constrained('livestock', 'animal_id');
            $table->foreign('farmer_id')->references('id')->on('farmers')->onDelete('cascade'); // ⭐ ADDED: Foreign key constraint
            $table->string('bull_tag')->nullable();
            $table->string('bull_name');
            $table->foreignId('breed_id')->constrained('breeds');
            $table->date('collection_date');
            $table->integer('dose_ml')->default(0.25);
            $table->integer('motility_percentage')->nullable();
            $table->decimal('cost_per_straw', 10, 2)->default(0);
            $table->string('source_supplier')->nullable();
            $table->boolean('used')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semen_inventory');
    }
};
