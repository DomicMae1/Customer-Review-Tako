<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename 'user' role to 'marketing' in roles table (tako-perusahaan)
        DB::connection('tako-perusahaan')
            ->table('roles')
            ->where('name', 'user')
            ->update(['name' => 'marketing']);

        // 2. Update role from 'user' to 'marketing' in perusahaan_user_roles table
        DB::connection('tako-perusahaan')
            ->table('perusahaan_user_roles')
            ->where('role', 'user')
            ->update(['role' => 'marketing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback renaming
        DB::connection('tako-perusahaan')
            ->table('roles')
            ->where('name', 'marketing')
            ->update(['name' => 'user']);

        DB::connection('tako-perusahaan')
            ->table('perusahaan_user_roles')
            ->where('role', 'marketing')
            ->update(['role' => 'user']);
    }
};
