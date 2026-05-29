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
        Schema::connection('tako-perusahaan')->table('perusahaan', function (Blueprint $table) {
            $table->boolean('is_ppjk')->default(false)->after('is_ktp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-perusahaan')->table('perusahaan', function (Blueprint $table) {
            $table->dropColumn('is_ppjk');
        });
    }
};
