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
            $table->string('bentuk_badan_usaha')->nullable()->change();
            $table->mediumText('alamat_lengkap')->nullable()->change();
            $table->string('kota')->nullable()->change();
            $table->mediumText('alamat_penagihan')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('top')->nullable()->change();
            $table->string('status_perpajakan')->nullable()->change();
            $table->string('nama_personal')->nullable()->change();
            $table->string('jabatan_personal')->nullable()->change();
            $table->string('email_personal')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-customer')->table('customers', function (Blueprint $table) {
            $table->string('bentuk_badan_usaha')->nullable(false)->change();
            $table->mediumText('alamat_lengkap')->nullable(false)->change();
            $table->string('kota')->nullable(false)->change();
            $table->mediumText('alamat_penagihan')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('top')->nullable(false)->change();
            $table->string('status_perpajakan')->nullable(false)->change();
            $table->string('nama_personal')->nullable(false)->change();
            $table->string('jabatan_personal')->nullable(false)->change();
            $table->string('email_personal')->nullable(false)->change();
        });
    }
};
