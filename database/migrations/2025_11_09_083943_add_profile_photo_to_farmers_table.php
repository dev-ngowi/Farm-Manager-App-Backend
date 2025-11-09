<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('profile_photo')->nullable()->after('years_experience');
            
            // Index for faster queries (optional)
            $table->index('profile_photo');
        });

        DB::statement("ALTER TABLE farmers COMMENT = 'Farmer profiles with photo, farm details, GPS location, and experience'");
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropIndex(['profile_photo']);
            $table->dropColumn('profile_photo');
        });
    }
};