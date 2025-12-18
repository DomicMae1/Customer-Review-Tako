<?php

namespace App\Http\Controllers;

use App\Models\Perusahaan;
use App\Models\Tenant;
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

        if (!$user->hasRole('admin')) {
            abort(403, 'Unauthorized access. Only admin can access this page.');
        }

        $perusahaans = Perusahaan::with(['user', 'users','tenant','tenant.domains'])->get();
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
        $validated = $request->validate([
            'nama_perusahaan' => 'required|string|max:255',

            'domain'          => 'required|string|max:255|unique:domains,domain',

            'id_User_1' => 'nullable|integer|exists:users,id', // manager
            'id_User_2' => 'nullable|integer|exists:users,id', // direktur
            'id_User_3' => 'nullable|integer|exists:users,id', // lawyer
            'id_User'   => 'nullable|integer|exists:users,id', // user

            'notify_1' => 'nullable|string',
            'notify_2' => 'nullable|string',

            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ]);

        $logoPath = null;
        if ($request->hasFile('company_logo')) {
            $logoPath = $request->file('company_logo')->store('company_logo', 'public');
        }

        $perusahaan = Perusahaan::create([
            'nama_perusahaan'   => $validated['nama_perusahaan'],
            'notify_1'          => $validated['notify_1'] ?? null,
            'notify_2'          => $validated['notify_2'] ?? null,
            'path_company_logo' => $logoPath,
        ]);

        $rawDomain = $validated['domain'];

        $tenantId = $validated['nama_perusahaan'];

        $rawTenant= Str::slug($tenantId);

        // $appDomain = env('APP_DOMAIN', 'registration.tako.co.id'); // Ambil dari .env (misal: registration.tako.co.id)
        // $baseSlug = Str::slug($validated['nama_perusahaan']);
        
        // // Pastikan subdomain unik di tabel DOMAINS
        // $subdomain = $baseSlug;
        // $counter = 1;
        // while (Domain::where('domain', "registration.{$subdomain}.{$appDomain}")->exists()) {
        //     $subdomain = "{$baseSlug}-{$counter}";
        //     $counter++;
        // }

        // $fullDomain = "{$subdomain}.{$appDomain}";

        // Buat Tenant Baru
        // ID Tenant kita samakan dengan subdomain agar mudah dibaca (opsional, bisa juga UUID)
        $tenant = Tenant::create([
            'id' => $rawTenant, 
            'perusahaan_id' => $perusahaan->id, // Sambungkan Relasi ke Perusahaan
        ]);

        // Buat Domain untuk Tenant tersebut
        $tenant->domains()->create([
            'domain' => $rawDomain,
        ]);

        // ========================================
        // 4. Simpan user roles
        // ========================================
        $roles = [
            $validated['id_User_1'] ?? null => 'manager',
            $validated['id_User_2'] ?? null => 'direktur',
            $validated['id_User_3'] ?? null => 'lawyer',
            $validated['id_User']   ?? null => 'user',
        ];

        foreach ($roles as $userId => $role) {
            if ($userId) {
                $perusahaan->users()->attach($userId, ['role' => $role]);

                // Opsional: Update id_perusahaan di tabel users jika perlu fallback
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
        $validated = $request->validate([
            'nama_perusahaan' => 'required|string|max:255',

            'domain'          => 'required|string|max:255|unique:domains,domain',

            // user roles
            'id_User'   => 'nullable|integer|exists:users,id',
            'id_User_1' => 'nullable|integer|exists:users,id',
            'id_User_2' => 'nullable|integer|exists:users,id',
            'id_User_3' => 'nullable|integer|exists:users,id',

            // notify
            'notify_1' => 'nullable|string',
            'notify_2' => 'nullable|string',

            // logo
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
        ]);

        // ========================================
        // 1. Update logo
        // ========================================
        if ($request->hasFile('company_logo')) {
            // Hapus logo lama
            if ($perusahaan->path_company_logo && Storage::disk('public')->exists($perusahaan->path_company_logo)) {
                Storage::disk('public')->delete($perusahaan->path_company_logo);
            }

            // Upload baru
            $perusahaan->path_company_logo = $request->file('company_logo')->store('company_logo', 'public');
        }

        // ========================================
        // 2. Update perusahaan
        // ========================================
        $perusahaan->update([
            'nama_perusahaan'   => $validated['nama_perusahaan'],
            'notify_1'          => $validated['notify_1'] ?? null,
            'notify_2'          => $validated['notify_2'] ?? null,
            'path_company_logo' => $perusahaan->path_company_logo,
        ]);

        $rawDomain = $validated['domain'];

        $tenantId = $validated['nama_perusahaan'];

        $rawTenant= Str::slug($tenantId);

        // Cari tenant berdasarkan perusahaan_id
        $tenant = Tenant::where('perusahaan_id', $perusahaan->id)->first();

        if (!$tenant) {
            // Jika tenant belum ada (kasus langka)
            $tenant = Tenant::create([
                'id' => Str::slug($rawTenant),
                'perusahaan_id' => $perusahaan->id,
            ]);
        }

        // Hapus semua domain lama
        $tenant->domains()->delete();

        // Buat domain baru
        $tenant->domains()->create([
            'domain' => $rawDomain,
        ]);

        $sync = [];

        if (!empty($validated['id_User'])) {
            $sync[$validated['id_User']] = ['role' => 'user'];
        }
        if (!empty($validated['id_User_1'])) {
            $sync[$validated['id_User_1']] = ['role' => 'manager'];
        }
        if (!empty($validated['id_User_2'])) {
            $sync[$validated['id_User_2']] = ['role' => 'direktur'];
        }
        if (!empty($validated['id_User_3'])) {
            $sync[$validated['id_User_3']] = ['role' => 'lawyer'];
        }

        $perusahaan->users()->sync($sync);

        return back()->with('success', 'Perusahaan berhasil diedit.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Perusahaan $perusahaan)
    {
        $perusahaan->users()->detach();

        $perusahaan->delete();

        return redirect()
            ->back()
            ->with('success', 'Perusahaan berhasil dihapus');
    }

    public function checkManagerExistence($idPerusahaan)
    {
        $perusahaan = Perusahaan::with(['users' => function ($query) {
            $query->wherePivot('role', 'manager');
        }])->find($idPerusahaan);

        return response()->json([
            'manager_exists' => $perusahaan && $perusahaan->users->isNotEmpty(),
        ]);
    }
}
