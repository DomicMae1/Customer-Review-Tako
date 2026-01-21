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

        foreach ($perusahaans as $data) {
            // 1. Buat Data Bisnis (Perusahaan)
            $perusahaan = Perusahaan::create([
                'nama_perusahaan' => $data['nama_perusahaan'],
                'notify_1' => $data['notify_1'],
            ]);

            // 2. Buat Data System (Tenant)
            $tenant = Tenant::create([
                'id' => $data['subdomain'],
                'perusahaan_id' => $perusahaan->id,
            ]);

            $appDomain = env('APP_DOMAIN'); // 'registration.tako.test'

            // 3. Logic Pembentukan Domain Baru
            // Kita ubah titik pertama '.' menjadi '.subdomain.'
            // 'registration.tako.test' -> 'registration.alpha.tako.test'
            $customDomain = Str::replaceFirst('.', '.' . $data['subdomain'] . '.', $appDomain);

            // 4. Simpan Domain
            $tenant->domains()->create([
                'domain' => $customDomain,
            ]);
        }
    }
}