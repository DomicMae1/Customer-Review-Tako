<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Database\Models\Domain;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        // 1. Ambil URL (Origin)
        $origin = $request->getHost();

        // 2. Logic: Origin -> Domain -> Tenant -> Perusahaan
        $companyData = null;

        // Cek tabel Domains
        $domainRecord = Domain::where('domain', $origin)->first();

        if ($domainRecord) {
            // Ambil Tenant dari Domain, lalu Perusahaan dari Tenant
            $company = $domainRecord->tenant?->perusahaan;

            if ($company) {
                $companyData = [
                    'nama_perusahaan' => $company->nama_perusahaan,
                    'path_company_logo' => $domainRecord->path_company_logo 
                        ? asset('storage/' . $domainRecord->path_company_logo) 
                        : null,
                ];
            }
        }

        // 3. Render Halaman Login dengan menyertakan data Company
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            // Kirim data perusahaan ke Frontend
            'company' => $companyData, 
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        // 1. Ambil URL yang sedang diakses (misal: alpha.customer-review-tako.test)
        $origin = $request->getHost();
        session(['login_origin' => $origin]);

        // $user = auth()->user(); // User tetap diautentikasi, tapi tidak dipakai untuk penentuan konteks perusahaan

        $company = null;

        // ---------------------------------------------------------------------
        // LOGIC BARU: Origin -> Domain -> Tenant -> Perusahaan
        // ---------------------------------------------------------------------

        // A. Cek di Tabel Domain
        // Mencari baris di tabel 'domains' yang kolom 'domain'-nya sama dengan $origin
        $domainRecord = \Stancl\Tenancy\Database\Models\Domain::where('domain', $origin)->first();
        
        if ($domainRecord) {
            // B. Masuk ke Kolom Tenant & C. Ambil ID Perusahaan
            // $domainRecord->tenant otomatis mencari di tabel 'tenants' berdasarkan tenant_id milik domain
            $tenant = $domainRecord->tenant;

            if ($tenant) {
                // D. Cek Tabel Perusahaan
                // $tenant->perusahaan otomatis mencari di tabel 'perusahaan' berdasarkan perusahaan_id milik tenant
                $company = $tenant->perusahaan;
            }
        }

        // ---------------------------------------------------------------------
        // SET SESSION
        // ---------------------------------------------------------------------
        
        // Jika rantai relasi (Domain -> Tenant -> Perusahaan) lengkap dan data ditemukan:
        if ($company) {
            session([
                'company_id'   => $company->id,               // ID dari tabel perusahaan
                'company_name' => $company->nama_perusahaan,  // Nama dari tabel perusahaan
                'company_logo' => $domainRecord->path_company_logo // Logo dari tabel perusahaan
                    ? asset('storage/' . $domainRecord->path_company_logo)
                    : null,
                'company_url'  => $origin,                    // URL akses saat ini
            ]);
        }

        return redirect()->intended(route('customer.index'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        $origin = session('login_origin', null);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Logout via Inertia -> always XHR
        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $origin 
                    ? 'http://' . $origin
                    : url('/'),
            ]);
        }

        // Logout via browser biasa
        if ($origin) {
            return redirect()->away('http://' . $origin);
        }

        return redirect('/');
    }
}
