<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Perusahaan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['user', 'manager', 'direktur', 'lawyer'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $users = [
            ['name' => 'John Doe', 'email' => 'marketing@gmail.com', 'password' => '1234', 'role' => 'user',     'id_perusahaan' => 1],
            ['name' => 'Rose Doe', 'email' => 'manager@gmail.com', 'password' => '1234', 'role' => 'manager',  'id_perusahaan' => 1],
            ['name' => 'Emi Rina', 'email' => 'direktur@gmail.com', 'password' => '1234', 'role' => 'direktur', 'id_perusahaan' => 1],
            ['name' => 'Tatsuya', 'email' => 'lawyer@gmail.com', 'password' => '1234', 'role' => 'lawyer',   'id_perusahaan' => 1],
            ['name' => 'don8', 'email' => 'don8@gmail.com', 'password' => '1234', 'role' => 'admin'],
            ['name' => 'David Marketing', 'email' => 'david.marketing@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'user'],
            ['name' => 'David Manager', 'email' => 'david.manager@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'manager'],
            ['name' => 'David Direktur', 'email' => 'david.direktur@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'direktur'],
            ['name' => 'David Lawyer', 'email' => 'david.lawyer@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'lawyer'],
            ['name' => 'David Admin', 'email' => 'david.yordan@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'admin'],
            ['name' => 'David Auditor', 'email' => 'david.auditor@tako.co.id', 'password' => 'CR_tako@2025', 'role' => 'auditor'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'id_perusahaan' => $data['id_perusahaan'] ?? null,
                ]
            );

            $user->syncRoles([$data['role']]);

            if (!empty($data['id_perusahaan'])) {
                $user->companies()->syncWithoutDetaching([
                    $data['id_perusahaan'] => ['role' => $data['role']],
                ]);
            }
        }
    }
}
