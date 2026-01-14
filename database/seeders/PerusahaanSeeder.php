<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Perusahaan;
use App\Models\Tenant; // Pastikan Model Tenant di-import

class PerusahaanSeeder extends Seeder
{
    public function run(): void
    {
        $perusahaans = [
            ['nama_perusahaan' => 'PT Alpha', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'alpha'],
            ['nama_perusahaan' => 'PT Beta', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'beta'],
            ['nama_perusahaan' => 'UD Cherry', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'cherry'],
            ['nama_perusahaan' => 'CV Delta', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'delta'],
            ['nama_perusahaan' => 'Amin Trans', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'amintrans'],
            ['nama_perusahaan' => 'Aji Logistic', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'ajilogistic'],
            ['nama_perusahaan' => 'Anugerah Multi Logistik', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'amlogistik'],
            ['nama_perusahaan' => 'Anugerah Berkat Transportasi', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'anugerahberkat'],
            ['nama_perusahaan' => 'Bersama Anugerah Pandawa', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'anugerahpandawa'],
            ['nama_perusahaan' => 'Anugerah Trans Maritim', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'atmtrans'],
            ['nama_perusahaan' => 'Berlian Anugerah Transportasi', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'berliananugerah'],
            ['nama_perusahaan' => 'Cahaya Abadi Logistik', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'cahayalogistik'],
            ['nama_perusahaan' => 'Pint Indonesia Logistik', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'pintlogistik'],
        ];

        foreach ($perusahaans as $data) {
            // 1. Buat Data Bisnis (Perusahaan)
            $perusahaan = Perusahaan::create([
                'nama_perusahaan' => $data['nama_perusahaan'],
                'notify_1' => $data['notify_1'],
                // 'path_company_logo' => ... (opsional jika ada)
            ]);

            // 2. Buat Data System (Tenant)
            // Ini akan memicu pembuatan database jika mode multi-database aktif
            $tenant = Tenant::create([
                'id' => $data['subdomain'], // ID Tenant kita samakan dengan subdomain biar mudah (misal: 'alpha')
                'perusahaan_id' => $perusahaan->id, // RELASI PENTING: Sambungkan ke ID Perusahaan
            ]);

            $appDomain = env('APP_DOMAIN');

            // 3. Buat Data Routing (Domain)
            // Ini agar URL alpha.localhost bisa diakses
            $tenant->domains()->create([
                'domain' => $data['subdomain'] . '.' . "{$appDomain}", // Hasil: alpha.localhost
            ]);
        }
    }
}