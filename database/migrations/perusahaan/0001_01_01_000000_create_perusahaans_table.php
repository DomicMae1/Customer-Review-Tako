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
        Schema::connection('tako-perusahaan')->create('perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('nama_perusahaan');
            $table->string('notify_1')->nullable();
            $table->string('notify_2')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('id_domain')->nullable()->after('nama_perusahaan');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tako-perusahaan')->dropIfExists('perusahaan');
    }
};
