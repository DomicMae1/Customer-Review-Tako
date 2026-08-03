<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class PerusahaanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user->can('perusahaan.view')) {
            abort(403, 'Unauthorized access.');
        }

        // 1. UBAH EAGER LOAD: Ganti 'tenant.domains' menjadi 'domain'
        // Pastikan model Perusahaan sudah ada: public function domain() { return $this->belongsTo(Domain::class, 'id_domain'); }
        $perusahaans = Perusahaan::with(['user', 'users', 'domain'])->get();

        $perusahaans->transform(function ($company) {
            // 2. AMBIL DARI RELASI LANGSUNG (id_domain)
            // Ini akan berhasil baik untuk PT Alpha (Pemilik) maupun PT Beta (Penumpang)
            $domainRecord = $company->domain; 

            $logoPath = $domainRecord ? $domainRecord->path_company_logo : null;
            $domainName = $domainRecord ? $domainRecord->domain : null;

            // 3. GENERATE FULL URL
            // Kita gabungkan protokol + nama domain + path storage
            if ($domainName && $logoPath) {
                // Cek protokol (HTTP/HTTPS)
                $protocol = request()->secure() ? 'https://' : 'http://';
                
                // Hasil: http://portal.tako.id/storage/company_logo/xxx.png
                $company->logo_url = $protocol . $domainName . '/storage/' . $logoPath;
            } else {
                $company->logo_url = null;
            }
                
            return $company;
        });

        $users = User::select('id', 'name')->get();

        return Inertia::render('company/page', [
            'companies' => $perusahaans,
            'users' => $users,
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('perusahaan.create')) {
            abort(403, 'Unauthorized access.');
        }
        $validated = $request->validate([
            'nama_perusahaan' => 'required|string|max:255',
            'domain'          => 'required|string|max:255',
            'id_User_1'       => 'nullable|integer|exists:users,id',
            'id_User_2'       => 'nullable|integer|exists:users,id',
            'id_User_3'       => 'nullable|integer|exists:users,id',
            'id_User'         => 'nullable|integer|exists:users,id',
            'notify_1'        => 'nullable|string',
            'notify_2'        => 'nullable|string',
            'is_npwp'         => 'nullable|boolean',
            'is_nib'          => 'nullable|boolean',
            'is_sptkp'        => 'nullable|boolean',
            'is_ktp'          => 'nullable|boolean',
            'is_ppjk'         => 'nullable|boolean',
            'company_logo'    => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ]);

        $rawDomain = $validated['domain'];
        
        // 1. BUAT PERUSAHAAN DULU (id_domain kita isi null dulu)
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => $validated['nama_perusahaan'],
            'id_domain'       => null, // Nanti kita update
            'notify_1'        => $validated['notify_1'] ?? null,
            'notify_2'        => $validated['notify_2'] ?? null,
            'is_npwp'         => (bool) ($request->input('is_npwp', false)),
            'is_nib'          => (bool) ($request->input('is_nib', false)),
            'is_sptkp'        => (bool) ($request->input('is_sptkp', false)),
            'is_ktp'          => (bool) ($request->input('is_ktp', false)),
            'is_ppjk'         => (bool) ($validated['is_ppjk'] ?? false),
        ]);

        // 2. Cek apakah Domain sudah ada?
        $existingDomain = Domain::where('domain', $rawDomain)->first();
        $domainIdToLink = null;

        if ($existingDomain) {
            // SKENARIO A: DOMAIN SUDAH ADA
            $domainIdToLink = $existingDomain->id;

        } else {
            // SKENARIO B: DOMAIN BARU (Belum ada)
            
            $tenantId = Str::slug($validated['nama_perusahaan']);
            
            if (\App\Models\Tenant::find($tenantId)) {
                $tenantId .= '-' . Str::random(4);
            }

            // Sekarang kita BISA mengisi 'perusahaan_id' karena $perusahaan sudah ada!
            $newTenant = \App\Models\Tenant::create([
                'id' => $tenantId,
                'perusahaan_id' => $perusahaan->id, // <-- KUNCINYA DISINI
            ]);

            $logoPath = null;
            if ($request->hasFile('company_logo')) {
                $logoPath = $request->file('company_logo')->store('company_logo', 'public');
            }

            // Buat Domain
            $newDomain = $newTenant->domains()->create([
                'domain' => $rawDomain,
                'path_company_logo' => $logoPath,
            ]);

            $domainIdToLink = $newDomain->id;
        }

        // 3. UPDATE PERUSAHAAN dengan ID DOMAIN yang benar
        $perusahaan->update([
            'id_domain' => $domainIdToLink
        ]);

        // ========================================
        // 4. Simpan user roles
        // ========================================
        $roles = [
            $validated['id_User_1'] ?? null => 'manager',
            $validated['id_User_2'] ?? null => 'direktur',
            $validated['id_User_3'] ?? null => 'lawyer',
            $validated['id_User']   ?? null => 'marketing',
        ];

        foreach ($roles as $userId => $role) {
            if ($userId) {
                $perusahaan->users()->attach($userId, ['role' => $role]);

                User::where('id', $userId)->update([
                    'id_perusahaan' => $perusahaan->id
                ]);
            }
        }

        return back()->with('success', 'Perusahaan berhasil ditambahkan. Domain: ' . $rawDomain);
    }

    /**
     * Display the specified resource.
     */
    public function show(Perusahaan $perusahaan)
    {
        return response()->json([
            'data' => $perusahaan->load(['user', 'users']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Perusahaan $perusahaan)
    {
        return response()->json([
            'data' => $perusahaan->load('users'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, Perusahaan $perusahaan)
    {
        $user = Auth::user();
        if (!$user->can('perusahaan.update')) {
            abort(403, 'Unauthorized access.');
        }
        $validated = $request->validate([
            'nama_perusahaan' => 'required|string|max:255',

            'domain'          => 'required|string|max:255',

            // user roles
            'id_User'   => 'nullable|integer|exists:users,id',
            'id_User_1' => 'nullable|integer|exists:users,id',
            'id_User_2' => 'nullable|integer|exists:users,id',
            'id_User_3' => 'nullable|integer|exists:users,id',

            // notify
            'notify_1' => 'nullable|string',
            'notify_2' => 'nullable|string',
            'is_npwp' => 'nullable|boolean',
            'is_nib' => 'nullable|boolean',
            'is_sptkp' => 'nullable|boolean',
            'is_ktp' => 'nullable|boolean',
            'is_ppjk' => 'nullable|boolean',

            // logo
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ]);

        // 2. Persiapan Data Domain & Tenant
        $targetDomainString = $validated['domain'];
        
        // Cari apakah domain tujuan sudah ada di database?
        // Pastikan import: use App\Models\Domain;
        $targetDomainRecord = Domain::where('domain', $targetDomainString)->first();
        
        $newLogoPath = null;
        $hasNewFile = $request->hasFile('company_logo');

        // 3. Handle File Upload (Simpan ke storage dulu)
        if ($hasNewFile) {
            $newLogoPath = $request->file('company_logo')->store('company_logo', 'public');
        }

        // 4. Logika Update Domain/Tenant
        if ($targetDomainRecord) {
            // === SKENARIO A: DOMAIN SUDAH ADA ===
            // Kita "menumpang" ke domain yang sudah ada.
            
            // Jika user upload logo baru, kita update logo di DOMAIN tersebut.
            // PERHATIAN: Ini akan mengubah logo untuk SEMUA perusahaan yang memakai domain ini.
            if ($hasNewFile) {
                // Hapus file lama fisik jika ada
                if ($targetDomainRecord->path_company_logo && Storage::disk('public')->exists($targetDomainRecord->path_company_logo)) {
                    Storage::disk('public')->delete($targetDomainRecord->path_company_logo);
                }
                // Update path di tabel domain
                $targetDomainRecord->update(['path_company_logo' => $newLogoPath]);
            }

            // Update FK di perusahaan ke ID domain yang ditemukan
            $perusahaan->id_domain = $targetDomainRecord->id;

        } else {
            // === SKENARIO B: DOMAIN BELUM ADA ===
            // Kita buat Tenant BARU dan Domain BARU
            
            $tenantId = Str::slug($validated['nama_perusahaan']);

            if (\App\Models\Tenant::find($tenantId)) {
                $tenantId .= '-' . Str::random(4);
            }

            $tenant = \App\Models\Tenant::create([
                'id' => $tenantId,
                'perusahaan_id' => $perusahaan->id,
            ]);

            $newDomainRecord = $tenant->domains()->create([
                'domain' => $targetDomainString,
                'path_company_logo' => $newLogoPath,
            ]);

            $perusahaan->id_domain = $newDomainRecord->id;
        }

        // 5. Update Data Dasar Perusahaan
        $perusahaan->nama_perusahaan = $validated['nama_perusahaan'];
        $perusahaan->notify_1 = $validated['notify_1'] ?? null;
        $perusahaan->notify_2 = $validated['notify_2'] ?? null;
        $perusahaan->is_npwp = (bool) ($request->input('is_npwp', false));
        $perusahaan->is_nib = (bool) ($request->input('is_nib', false));
        $perusahaan->is_sptkp = (bool) ($request->input('is_sptkp', false));
        $perusahaan->is_ktp = (bool) ($request->input('is_ktp', false));
        $perusahaan->is_ppjk = (bool) ($validated['is_ppjk'] ?? false);
        $perusahaan->save(); // Simpan semua perubahan

        // 6. Sync User Roles (Logic tetap sama)
        $sync = [];
        if (!empty($validated['id_User']))   $sync[$validated['id_User']]   = ['role' => 'marketing'];
        if (!empty($validated['id_User_1'])) $sync[$validated['id_User_1']] = ['role' => 'manager'];
        if (!empty($validated['id_User_2'])) $sync[$validated['id_User_2']] = ['role' => 'direktur'];
        if (!empty($validated['id_User_3'])) $sync[$validated['id_User_3']] = ['role' => 'lawyer'];

        $changes = $perusahaan->users()->sync($sync);

        // Update id_perusahaan di tabel User untuk user yang baru di-assign
        $userIdsToCheck = array_keys($sync);
        if (!empty($userIdsToCheck)) {
            \App\Models\User::whereIn('id', $userIdsToCheck)->update([
                'id_perusahaan' => $perusahaan->id
            ]);
        }

        // Null-kan id_perusahaan untuk user yang dilepas (hanya jika masih mengarah ke perusahaan ini)
        if (!empty($changes['detached'])) {
            \App\Models\User::whereIn('id', $changes['detached'])
                ->where('id_perusahaan', $perusahaan->id)
                ->update(['id_perusahaan' => null]);
        }

        return back()->with('success', 'Perusahaan berhasil diedit.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Perusahaan $perusahaan)
    {
        $user = Auth::user();
        if (!$user->can('perusahaan.delete')) {
            abort(403, 'Unauthorized access.');
        }
        $perusahaan->users()->detach();

        $perusahaan->delete();

        return redirect()
            ->back()
            ->with('success', 'Perusahaan berhasil dihapus');
    }

    public function checkManagerExistence($idPerusahaan)
    {
        $user = Auth::user();
        if (!$user->can('perusahaan.view') && !$user->can('customer.create') && !$user->can('customer.update')) {
            abort(403, 'Unauthorized access.');
        }
        $perusahaan = Perusahaan::with(['users' => function ($query) {
            $query->wherePivot('role', 'manager');
        }])->find($idPerusahaan);

        return response()->json([
            'manager_exists' => $perusahaan && $perusahaan->users->isNotEmpty(),
        ]);
    }
}
