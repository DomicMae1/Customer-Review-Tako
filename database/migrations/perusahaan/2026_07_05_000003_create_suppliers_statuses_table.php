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
        Schema::connection('tako-perusahaan')->create('suppliers_statuses', function (Blueprint $table) {
            $table->id('id_Supplier'); 

            $table->foreignId('id_user')->constrained('users')->onDelete('cascade');

            $table->timestamp('submit_1_timestamps')->nullable(); 
            $table->string('submit_1_nama_file')->nullable(); 
            $table->string('submit_1_path')->nullable(); 

            $table->foreignId('status_1_by')->nullable()->constrained('users')->onDelete('set null'); 
            $table->timestamp('status_1_timestamps')->nullable();
            $table->text('status_1_keterangan')->nullable(); 
            $table->string('status_1_nama_file')->nullable(); 
            $table->string('status_1_path')->nullable();

            $table->foreignId('status_2_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('status_2_timestamps')->nullable();
            $table->text('status_2_keterangan')->nullable();
            $table->string('status_2_nama_file')->nullable(); 
            $table->string('status_2_path')->nullable(); 

            $table->foreignId('status_3_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('status_3_timestamps')->nullable();
            $table->text('status_3_keterangan')->nullable();
            $table->text('status_3')->nullable();
            $table->string('submit_3_nama_file')->nullable();  
            $table->string('submit_3_path')->nullable(); 

            $table->foreignId('status_4_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('status_4_timestamps')->nullable();
            $table->text('status_4_keterangan')->nullable();
            $table->string('status_4_nama_file')->nullable(); 
            $table->string('status_4_path')->nullable(); 

            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tako-perusahaan')->dropIfExists('suppliers_statuses');
    }
};
