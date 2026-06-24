<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExternalCustomerReceiveController extends Controller
{
    /**
     * Receive customer data from external application.
     * 
     * Endpoint: POST /api/customer/receive
     * Auth: Sanctum (Bearer token)
     * 
     * Only receives and saves customer data.
     * - UID is auto-generated, any sent uid is ignored/overwritten
     * - Only customer fields are accepted and saved
     * - Uses same connection and database as existing customer feature
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $idPerusahaan = $user->id_perusahaan;

        if (!$idPerusahaan) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => [
                    'id_perusahaan' => [
                        'User token ini belum memiliki perusahaan.',
                    ],
                ],
            ], 422);
        }

        $perusahaan = Perusahaan::find($idPerusahaan);

        if (!$perusahaan) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => [
                    'id_perusahaan' => [
                        'Perusahaan tidak ditemukan.',
                    ],
                ],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'kategori_usaha' => 'required|string',
            'nama_perusahaan' => 'required|string',
            'bentuk_badan_usaha' => 'required|string',
            'alamat_lengkap' => 'required|string',
            'kota' => 'required|string',
            'no_telp' => 'nullable|string',
            'no_fax' => 'nullable|string',
            'alamat_penagihan' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|string',
            'top' => 'nullable|string',
            'status_perpajakan' => 'nullable|string',
            'no_npwp' => 'nullable|string',
            'no_npwp_16' => 'nullable|string',
            'nib' => 'nullable|string',

            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',

            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable|string',
            'email_personal' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::connection('tako-customer')->beginTransaction();
            DB::connection('tako-perusahaan')->beginTransaction();

            $customer = Customer::create(array_merge($validated, [
                'uid' => $this->generateCustomerUid(),
                'id_user' => $user->id,
                'id_perusahaan' => $idPerusahaan,
            ]));

            DB::connection('tako-perusahaan')->table('customers_statuses')->insert([
                'id_Customer' => $customer->id,
                'id_user' => $user->id,
                'submit_1_timestamps' => null,
                'status_1_by' => null,
                'status_1_timestamps' => null,
                'status_1_keterangan' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('tako-perusahaan')->commit();
            DB::connection('tako-customer')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil disimpan.',
                'data' => [
                    'id' => $customer->id,
                    'uid' => $customer->uid,
                    'id_user' => $customer->id_user,
                    'id_perusahaan' => $customer->id_perusahaan,

                    'kategori_usaha' => $customer->kategori_usaha,
                    'nama_perusahaan' => $customer->nama_perusahaan,
                    'bentuk_badan_usaha' => $customer->bentuk_badan_usaha,
                    'alamat_lengkap' => $customer->alamat_lengkap,
                    'kota' => $customer->kota,
                    'no_telp' => $customer->no_telp,
                    'no_fax' => $customer->no_fax,
                    'alamat_penagihan' => $customer->alamat_penagihan,
                    'email' => $customer->email,
                    'website' => $customer->website,
                    'top' => $customer->top,
                    'status_perpajakan' => $customer->status_perpajakan,
                    'no_npwp' => $customer->no_npwp,
                    'no_npwp_16' => $customer->no_npwp_16,
                    'nib' => $customer->nib,

                    'nama_pj' => $customer->nama_pj,
                    'no_ktp_pj' => $customer->no_ktp_pj,
                    'no_telp_pj' => $customer->no_telp_pj,

                    'nama_personal' => $customer->nama_personal,
                    'jabatan_personal' => $customer->jabatan_personal,
                    'no_telp_personal' => $customer->no_telp_personal,
                    'email_personal' => $customer->email_personal,

                    'created_at' => $customer->created_at,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::connection('tako-perusahaan')->rollBack();
            DB::connection('tako-customer')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan customer.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function generateCustomerUid(): string
    {
        do {
            $uid = now()->format('Ym') . random_int(100000, 999999);
        } while (Customer::where('uid', $uid)->exists());

        return $uid;
    }
}
