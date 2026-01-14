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
        Schema::connection('tako-customer')->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->nullable()->index()->after('id');

            $table->unsignedBigInteger('id_user');         
            $table->unsignedBigInteger('id_perusahaan'); 

            $table->string('kategori_usaha');
            $table->string('nama_perusahaan');
            $table->string('bentuk_badan_usaha');
            $table->mediumText('alamat_lengkap');
            $table->string('kota');
            $table->string('no_telp')->nullable();
            $table->string('no_fax')->nullable();
            $table->mediumText('alamat_penagihan');
            $table->string('email');
            $table->string('website')->nullable();
            $table->string('top');
            $table->string('status_perpajakan');
            $table->string('no_npwp')->nullable();
            $table->string('no_npwp_16')->nullable();

            $table->string('nama_pj');
            $table->string('no_ktp_pj');
            $table->string('no_telp_pj')->nullable();

            $table->string('nama_personal');
            $table->string('jabatan_personal');
            $table->string('no_telp_personal')->nullable();
            $table->string('email_personal');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-customer')->dropIfExists('customers');
    }
};
