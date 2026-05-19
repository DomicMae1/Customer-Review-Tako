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
            $table->string('nama_pj')->nullable()->change();
            $table->string('no_ktp_pj')->nullable()->change();
            $table->string('no_telp_pj')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-customer')->table('customers', function (Blueprint $table) {
            $table->string('nama_pj')->nullable(false)->change();
            $table->string('no_ktp_pj')->nullable(false)->change();
            $table->string('no_telp_pj')->nullable(false)->change();
        });
    }
};
