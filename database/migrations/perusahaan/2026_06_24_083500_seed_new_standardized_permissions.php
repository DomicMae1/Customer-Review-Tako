<?php

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\RoleAndPermissionSeeder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Run the seeder to ensure all new permissions are created and mapped to roles
        $seeder = new RoleAndPermissionSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not drop roles or permissions to avoid data loss
    }
};
