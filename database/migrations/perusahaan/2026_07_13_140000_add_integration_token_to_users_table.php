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
        Schema::connection('tako-perusahaan')->table('users', function (Blueprint $table) {
            $table->string('integration_token')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-perusahaan')->table('users', function (Blueprint $table) {
            $table->dropColumn('integration_token');
        });
    }
};
