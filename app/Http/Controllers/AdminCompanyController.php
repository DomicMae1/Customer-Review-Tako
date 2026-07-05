<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminCompanyController extends Controller
{
    /**
     * Set the active company context for the admin user.
     * Stores the chosen company_id in the session so all subsequent
     * data queries are scoped to that company.
     *
     * POST /admin/set-company
     */
    public function setCompany(Request $request)
    {
        $user = Auth::user();

        // Hanya admin yang boleh mengakses endpoint ini
        if (!$user->hasRole('admin')) {
            abort(403, 'Unauthorized: hanya admin yang dapat memilih perusahaan.');
        }

        $request->validate([
            'company_id' => ['nullable', 'integer'],
        ]);

        $companyId = $request->input('company_id');

        if ($companyId === null || $companyId === '' || $companyId == 0) {
            // Null = Semua Perusahaan (reset filter)
            session()->forget('admin_active_company_id');
            session()->forget('admin_active_company_name');
        } else {
            // Validasi perusahaan ada
            $perusahaan = Perusahaan::find($companyId);
            if (!$perusahaan) {
                abort(422, 'Perusahaan tidak ditemukan.');
            }

            session(['admin_active_company_id' => $perusahaan->id]);
            session(['admin_active_company_name' => $perusahaan->nama_perusahaan]);
        }

        return back();
    }
}