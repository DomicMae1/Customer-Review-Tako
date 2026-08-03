<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Perusahaan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Exceptions\UnauthorizedException;

class BankSupplierController extends Controller
{
    /**
     * Field wajib yang harus terisi agar supplier dianggap "Lengkap".
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
     * Display a listing of suppliers for Bank Supplier view.
     */
    public function index(Request $request)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.bank.view')) {
            throw UnauthorizedException::forPermissions(['supplier.bank.view']);
        }

        $query = Supplier::with(['perusahaan'])->whereNotNull('id_perusahaan');

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

        $suppliers = $query->orderBy('created_at', 'desc')->get();

        $supplierData = $suppliers->map(function (Supplier $supplier) {
            $noTelp    = $supplier->formatted_no_telp ?: null;
            $noTelpPic = $supplier->formatted_no_telp_personal ?: null;

            return [
                'id'                    => $supplier->id,
                'supplier_category'     => $supplier->supplier_category ?? '-',
                'nama_perusahaan'       => $supplier->nama_perusahaan ?? '-',
                'kategori_usaha'        => $supplier->kategori_usaha ?? '-',
                'bentuk_badan_usaha'    => $supplier->bentuk_badan_usaha ?? '-',
                'kota'                  => $supplier->kota ?? '-',
                'no_telp'               => $noTelp ?? '-',
                'npwp'                  => $supplier->no_npwp ?? '-',
                'npwp_16'               => $supplier->no_npwp_16 ?? '-',
                'nib'                   => $supplier->nib ?? '-',
                'pic'                   => $supplier->nama_personal ?? '-',
                'jabatan_pic'           => $supplier->jabatan_personal ?? '-',
                'no_telp_pic'           => $noTelpPic ?? '-',
                'email_pic'             => $supplier->email_personal ?? '-',
                'entitas'               => $this->isSupplierComplete($supplier) ? 'Lengkap' : 'Belum Lengkap',
                'nama_perusahaan_induk' => $supplier->perusahaan?->nama_perusahaan ?? '-',
            ];
        });

        return Inertia::render('bank_supplier/page', [
            'suppliers' => $supplierData,
            'search'    => $search ?? '',
            'flash'     => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Cek apakah semua field wajib supplier sudah terisi.
     */
    private function isSupplierComplete(Supplier $supplier): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $supplier->$field;

            if ($value === null || $value === '' || $value === []) {
                return false;
            }
        }

        return true;
    }
}