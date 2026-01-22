<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Perusahaan;
use App\Models\Tenant; // Pastikan Model Tenant di-import
use Illuminate\Support\Str;

class PerusahaanSeeder extends Seeder
{
    public function run(): void
    {
        $perusahaans = [
            ['nama_perusahaan' => 'PT Alpha', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'alpha'],
            ['nama_perusahaan' => 'PT Beta', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'beta'],
            ['nama_perusahaan' => 'UD Cherry', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'cherry'],
            ['nama_perusahaan' => 'CV Delta', 'notify_1' => 'ardonyunors147@gmail.com', 'subdomain' => 'delta'],
        ];

        $appDomain = env('APP_DOMAIN');

        foreach ($perusahaans as $data) {
            
            // 1. Buat Perusahaan
            $perusahaan = Perusahaan::create([
                'nama_perusahaan' => $data['nama_perusahaan'],
                'notify_1' => $data['notify_1'],
            ]);

            // 2. Buat Tenant
            $tenant = Tenant::create([
                'id' => $data['subdomain'],
                'perusahaan_id' => $perusahaan->id,
            ]);

            // 3. Logic Pembentukan Domain Baru (PERBAIKAN DISINI)
            // Cukup gabungkan: subdomain + titik + app_domain
            // Hasil: alpha.customer-review-tako.test
            $customDomain = $data['subdomain'] . '.' . $appDomain;

            // 4. Simpan Domain
            $domainRecord = $tenant->domains()->create([
                'domain' => $customDomain,
            ]);

            // 5. Update Perusahaan dengan ID Domain
            $perusahaan->update([
                'id_domain' => $domainRecord->id,
            ]);
        }
    }
}