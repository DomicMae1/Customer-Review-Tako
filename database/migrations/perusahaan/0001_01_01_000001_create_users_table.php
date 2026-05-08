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
        Schema::connection('tako-perusahaan')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('uid')->nullable()->unique()->after('name');
            $table->string('NIK')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->unsignedBigInteger('id_perusahaan')->nullable();
            $table->foreign('id_perusahaan')->references('id')->on('perusahaan')->onDelete('set null');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('tako-perusahaan')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('tako-perusahaan')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-perusahaan')->dropIfExists('users');
        Schema::connection('tako-perusahaan')->dropIfExists('password_reset_tokens');
        Schema::connection('tako-perusahaan')->dropIfExists('sessions');
    }
};
