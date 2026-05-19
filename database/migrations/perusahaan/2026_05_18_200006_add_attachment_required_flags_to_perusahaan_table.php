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
            $table->boolean('is_npwp')->default(true)->after('data');
            $table->boolean('is_nib')->default(true)->after('is_npwp');
            $table->boolean('is_sptkp')->default(false)->after('is_nib');
            $table->boolean('is_ktp')->default(true)->after('is_sptkp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-perusahaan')->table('perusahaan', function (Blueprint $table) {
            $table->dropColumn([
                'is_npwp',
                'is_nib',
                'is_sptkp',
                'is_ktp',
            ]);
        });
    }
};
