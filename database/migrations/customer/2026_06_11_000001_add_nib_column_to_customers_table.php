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
        Schema::connection('tako-customer')->table('customers', function (Blueprint $table) {
            $table->string('nib')->nullable()->after('no_npwp_16');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-customer')->table('customers', function (Blueprint $table) {
            $table->dropColumn('nib');
        });
    }
};
