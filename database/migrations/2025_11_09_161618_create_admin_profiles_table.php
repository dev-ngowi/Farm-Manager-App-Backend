<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->enum('admin_level', [
                'Super Admin',
                'System Admin',
                'Data Manager',
                'Support Staff'
            ])->default('System Admin');

            $table->string('department')->nullable();

            $table->json('assigned_regions')->nullable();

            $table->boolean('can_approve_vets')->default(true);
            $table->boolean('can_approve_researchers')->default(true);
            $table->boolean('can_manage_users')->default(true);
            $table->boolean('can_access_reports')->default(true);
            $table->boolean('can_export_data')->default(false);
            $table->boolean('can_modify_system_config')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('admin_level');
            $table->index('can_export_data');
            $table->index('can_modify_system_config');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_profiles');
    }
};
