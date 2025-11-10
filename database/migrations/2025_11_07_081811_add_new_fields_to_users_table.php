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
        Schema::table('users', function (Blueprint $table) {
            // Add new columns (only if they don't exist already)
            $table->string('firstname', 100)->after('username')->nullable(false);
            $table->string('lastname', 100)->after('firstname')->nullable(false);

            // Change name → keep for backward compatibility or drop later
            // We'll keep 'name' for now, you can remove it in a future migration if needed

            $table->string('phone_number', 20)->unique()->nullable()->after('email');

            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login')->nullable()->after('email_verified_at');

            // Optional: If you want to remove remember_token (not in new design)
            // $table->dropRememberToken();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'firstname',
                'lastname',
                'phone_number',
                'is_active',
                'last_login'
            ]);

            // Optional: restore remember_token if needed
            // $table->rememberToken();
        });
    }
};
