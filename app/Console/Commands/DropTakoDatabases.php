<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DropTakoDatabases extends Command
{
    /**
     * Nama command
     */
    protected $signature = 'db:drop-tako {--force}';

    /**
     * Deskripsi command
     */
    protected $description = 'Drop database tako-perusahaan dan tako-customer (PostgreSQL)';

    /**
     * Execute command
     */
    public function handle()
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('❌ Tidak boleh dijalankan di production tanpa --force');
            return Command::FAILURE;
        }

        $this->warn('⚠️ PERINGATAN: Ini akan menghapus database secara permanen!');

        if (!$this->confirm('Apakah Anda yakin ingin melanjutkan?')) {
            $this->info('❎ Dibatalkan.');
            return Command::SUCCESS;
        }

        try {
            $db1 = config('database.connections.tako-perusahaan.database');
            $db2 = config('database.connections.tako-customer.database');

            if (empty($db1) && empty($db2)) {
                $this->error('❌ Nama database tidak ditemukan di config.');
                return Command::FAILURE;
            }

            /*
             |--------------------------------------------------------------
             | Buat koneksi sementara ke database "postgres"
             | memakai credential DB_TAKO_* agar tidak pakai role root
             |--------------------------------------------------------------
             */
            Config::set('database.connections.pgsql_admin', [
                'driver' => 'pgsql',
                'host' => env('DB_TAKO_HOST', '127.0.0.1'),
                'port' => env('DB_TAKO_PORT', '5432'),
                'database' => 'postgres', // connect ke database lain dulu
                'username' => env('DB_TAKO_USERNAME'),
                'password' => env('DB_TAKO_PASSWORD'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);

            DB::purge('pgsql_admin');
            $connection = DB::connection('pgsql_admin');
            $connection->getPdo();

            if (!empty($db1)) {
                $this->info("🔌 Memutus koneksi ke database: {$db1}");
                $safeDb1 = str_replace("'", "''", $db1);

                $connection->statement("
                    SELECT pg_terminate_backend(pid)
                    FROM pg_stat_activity
                    WHERE datname = '{$safeDb1}'
                      AND pid <> pg_backend_pid();
                ");

                $this->info("🗑️ Menghapus database: {$db1}");
                $connection->statement('DROP DATABASE IF EXISTS "' . str_replace('"', '""', $db1) . '"');
            }

            if (!empty($db2)) {
                $this->info("🔌 Memutus koneksi ke database: {$db2}");
                $safeDb2 = str_replace("'", "''", $db2);

                $connection->statement("
                    SELECT pg_terminate_backend(pid)
                    FROM pg_stat_activity
                    WHERE datname = '{$safeDb2}'
                      AND pid <> pg_backend_pid();
                ");

                $this->info("🗑️ Menghapus database: {$db2}");
                $connection->statement('DROP DATABASE IF EXISTS "' . str_replace('"', '""', $db2) . '"');
            }

            $this->info('✅ Semua database berhasil dihapus!');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Terjadi kesalahan:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}