<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Perusahaan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;

class BankCustomerController extends Controller
{
    /**
     * Field wajib yang harus terisi agar customer dianggap "Lengkap".
     * Mengacu pada rule required di CustomerController::store().
     */
    private const REQUIRED_FIELDS = [
        'kategori_usaha',
        'nama_perusahaan',
        'bentuk_badan_usaha',
        'alamat_lengkap',
        'kota',
        'alamat_penagihan',
        'email',
    ];

    /**
     * Display a listing of customers for Bank Customer view.
     */
    public function index(Request $request)
    {
        $user = auth('web')->user();

        if (!$user->can('customer.bank.view')) {
            throw UnauthorizedException::forPermissions(['customer.bank.view']);
        }

        $query = Customer::with(['perusahaan'])->whereNotNull('id_perusahaan');

        // Filter scope berdasarkan role
        if ($user->hasRole('marketing')) {
            if ($user->id_perusahaan) {
                $query->where('id_perusahaan', $user->id_perusahaan);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole(['manager', 'direktur', 'lawyer', 'auditor'])) {
            $isGlobal = ($user->hasRole(['lawyer', 'auditor', 'direktur']) && empty($user->id_perusahaan));

            if (!$isGlobal) {
                $perusahaanIds = DB::connection('tako-perusahaan')
                    ->table('perusahaan_user_roles')
                    ->where('user_id', $user->id)
                    ->pluck('id_perusahaan')
                    ->toArray();

                if (!empty($perusahaanIds)) {
                    $query->whereIn('id_perusahaan', $perusahaanIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        } elseif ($user->hasRole('admin')) {
            // Admin dapat memilih perusahaan tertentu dari selector di header.
            // Jika null → tampilkan semua data perusahaan (kecuali yang id_perusahaan nya null).
            $adminActiveCompanyId = session('admin_active_company_id');
            if ($adminActiveCompanyId) {
                $query->where('id_perusahaan', $adminActiveCompanyId);
            }
        }

        // Filter search opsional berdasarkan nama perusahaan
        $search = $request->input('search');
        if ($search) {
            $query->where('nama_perusahaan', 'like', '%' . $search . '%');
        }

        $customers = $query->orderBy('created_at', 'desc')->get();

        $customerData = $customers->map(function (Customer $customer) {
            $noTelp    = $customer->formatted_no_telp ?: null;
            $noTelpPic = $customer->formatted_no_telp_personal ?: null;

            return [
                'id'                    => $customer->id,
                'nama_perusahaan'       => $customer->nama_perusahaan ?? '-',
                'kategori_usaha'        => $customer->kategori_usaha ?? '-',
                'bentuk_badan_usaha'    => $customer->bentuk_badan_usaha ?? '-',
                'kota'                  => $customer->kota ?? '-',
                'no_telp'               => $noTelp ?? '-',
                'npwp'                  => $customer->no_npwp ?? '-',
                'npwp_16'               => $customer->no_npwp_16 ?? '-',
                'nib'                   => $customer->nib ?? '-',
                'pic'                   => $customer->nama_personal ?? '-',
                'jabatan_pic'           => $customer->jabatan_personal ?? '-',
                'no_telp_pic'           => $noTelpPic ?? '-',
                'email_pic'             => $customer->email_personal ?? '-',
                'entitas'               => $this->isCustomerComplete($customer) ? 'Lengkap' : 'Belum Lengkap',
                'nama_perusahaan_induk' => $customer->perusahaan?->nama_perusahaan ?? '-',
            ];
        });

        return Inertia::render('bank_customer/page', [
            'customers' => $customerData,
            'search'    => $search ?? '',
            'flash'     => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Cek apakah semua field wajib customer sudah terisi.
     * Field wajib mengacu pada rule required di CustomerController::store().
     */
    private function isCustomerComplete(Customer $customer): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $customer->$field;

            if ($value === null || $value === '' || $value === []) {
                return false;
            }
        }

        return true;
    }
}