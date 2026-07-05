<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLink;
use App\Models\SupplierAttach;
use App\Models\SuppliersStatus;
use App\Models\Perusahaan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Barryvdh\DomPDF\Facade\Pdf;
use Clegginabox\PDFMerger\PDFMerger;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.view')) {
            throw UnauthorizedException::forPermissions(['supplier.view']);
        }

        // --- Cek user tanpa perusahaan ---
        if ($user->hasRole(['marketing', 'manager', 'direktur']) && empty($user->id_perusahaan)) {
            return Inertia::render('m_supplier/page', [
                'suppliers' => [],
                'company' => null,
                'flash' => ['success' => null, 'error' => 'Anda belum masuk di perusahaan manapun.'],
            ]);
        }

        // --- 1. Setup Query Dasar ---
        $query = Supplier::with([
            'creator', 'perusahaan', 'status',
            'status.submit1By', 'status.status1Approver',
            'status.status2Approver', 'status.status3Approver',
            'supplier_links'
        ]);

        // --- 2. Filter Scope Perusahaan ---
        if ($user->hasRole('marketing')) {
            if ($user->id_perusahaan) {
                $query->where('id_perusahaan', $user->id_perusahaan)
                      ->where('id_user', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole(['manager', 'direktur', 'lawyer', 'auditor'])) {
            $isLawyerGlobal = ($user->hasRole(['lawyer', 'auditor', 'direktur']) && empty($user->id_perusahaan));
            if (!$isLawyerGlobal) {
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
            // Jika sudah memilih (admin_active_company_id ada di session), filter by perusahaan.
            // Jika belum memilih (null), tampilkan semua data tanpa filter perusahaan.
            $adminActiveCompanyId = session('admin_active_company_id');
            if ($adminActiveCompanyId) {
                $query->where('id_perusahaan', $adminActiveCompanyId);
            }
        }

        // =====================================================================
        // 3. LOGIC WORKFLOW (History Mode + Strict Hierarchy)
        // =====================================================================
        
        $statusTable = 'suppliers_statuses'; 

        // --- A. ROLE USER (MARKETING) ---
        // Lihat semua history buatan sendiri.
        if ($user->hasRole('marketing')) {
            // Logic sudah tercover di filter scope perusahaan (where id_user)
        }

        // --- B. ROLE MANAGER ---
        // Lihat Inbox (Kiriman User) + Buatan Sendiri.
        // TAPI: Tidak boleh lihat data buatan Direktur.
        elseif ($user->hasRole('manager')) {
            
            // Inbox: Data yang sudah disubmit
            $submittedIds = DB::connection('tako-perusahaan')
                ->table($statusTable)
                ->whereNotNull('submit_1_timestamps')
                ->pluck('id_Supplier')
                ->toArray();

            // Blokir data buatan Direktur
            $bossIds = User::role('direktur')->pluck('id')->toArray(); 

            $query->where(function($q) use ($submittedIds, $user, $bossIds) {
                // 1. Inbox (Kecuali punya bos)
                $q->where(function($sub) use ($submittedIds, $bossIds) {
                    $sub->whereIn('id', $submittedIds)
                        ->whereNotIn('id_user', $bossIds);
                })
                // 2. Buatan Sendiri
                ->orWhere('id_user', $user->id);
            });
        }

        // --- C. ROLE DIREKTUR / LAWYER / AUDITOR ---
        // Lihat Data Matang (Sudah Verif Manager) + Buatan Sendiri.
        elseif ($user->hasRole(['direktur', 'lawyer', 'auditor'])) { 
            
            // Step 1: Cari ID Perusahaan yang MEMILIKI Manager aktif
            $companiesWithManager = User::role('manager')
                ->whereNotNull('id_perusahaan')
                ->pluck('id_perusahaan')
                ->unique()
                ->toArray();

            // Step 2: Ambil ID Supplier berdasarkan status
            
            // A. Data Verified (Sudah diapprove Manager)
            $verifiedByManagerIds = DB::connection('tako-perusahaan')
                ->table($statusTable)
                ->whereNotNull('status_1_timestamps')
                ->pluck('id_Supplier')
                ->toArray();

            // B. Data Submitted (Baru disubmit User/Marketing)
            $submittedByUserIds = DB::connection('tako-perusahaan')
                ->table($statusTable)
                ->whereNotNull('submit_1_timestamps')
                ->pluck('id_Supplier')
                ->toArray();

            // Step 3: Gabungkan Filter
            $query->where(function($q) use ($verifiedByManagerIds, $submittedByUserIds, $companiesWithManager, $user) {
                
                // 1. Buatan Sendiri (Selalu muncul)
                $q->where('id_user', $user->id)

                // 2. Flow Normal (Ada Manager): Tampilkan jika sudah diapprove Manager
                // (Berlaku untuk semua perusahaan, baik punya manager atau tidak, jika sudah status_1 pasti aman)
                  ->orWhereIn('id', $verifiedByManagerIds)

                // 3. Flow Bypass (Tidak Ada Manager): Tampilkan jika sudah disubmit User
                // SYARAT: ID Perusahaan dari data tersebut TIDAK ADA dalam list $companiesWithManager
                  ->orWhere(function($subQ) use ($submittedByUserIds, $companiesWithManager) {
                      $subQ->whereIn('id', $submittedByUserIds)
                           ->whereNotIn('id_perusahaan', $companiesWithManager);
                  });
            });
        }

        // =====================================================================

        $suppliers = $query->orderBy('created_at', 'desc')->get();

        // --- 4. MAPPING DATA (PERBAIKAN UNTUK FRONTEND) ---
        $supplierData = $suppliers->map(function ($supplier) {
            $status = $supplier->status;
            $tanggal = null;
            $label = null;
            $userName = null;
            $note = null;

            if ($status?->status_3_timestamps) {
                $tanggal = $status->status_3_timestamps;
                $label = 'direview';
                $userName = $status->status3Approver?->name ?? '-';
                $note = $status->status_3_keterangan;
            } elseif ($status?->status_2_timestamps) {
                $tanggal = $status->status_2_timestamps;
                $label = 'diketahui';
                $userName = $status->status2Approver?->name ?? '-';
                $note = $status->status_2_keterangan;
            } elseif ($status?->status_1_timestamps) {
                $tanggal = $status->status_1_timestamps;
                $label = 'diverifikasi';
                $userName = $status->status1Approver?->name ?? '-';
                $note = $status->status_1_keterangan;
            } elseif ($status?->submit_1_timestamps) {
                $tanggal = $status->submit_1_timestamps;
                $label = 'disubmit';
                $userName = $status->submit1By?->name ?? '-';
            } else {
                $tanggal = $supplier->created_at;
                $label = 'diinput';
                $userName = $supplier->creator?->name ?? '-';
            }

            // Fix Invalid Date: Pastikan tanggal dikirim sebagai string ISO
            $formattedDate = $tanggal ? \Carbon\Carbon::parse($tanggal)->toIso8601String() : null;

            return [
                'id' => $supplier->id,
                'nama_perusahaan' => $supplier->perusahaan?->nama_perusahaan ?? '-',
                'nama_supplier' => $supplier->nama_perusahaan ?? '-',
                'tanggal_status' => $tanggal,
                'status_label' => $label,
                'status' => $status?->status_3 ?? '-',
                
                // 6. Tanggal Status (Untuk memperbaiki "Invalid Date")
                // Frontend membaca ini untuk menampilkan "disubmit pada [TANGGAL]"
                'tanggal_status' => $formattedDate, 
                'created_at' => $supplier->created_at, // Fallback

                // 7. Status Review (Approved/Rejected)                'status' => $status?->status_3 ?? '-',
                'nama_user' => $userName,
                'creator_name' => $supplier->creator?->name ?? '-',
                'no_telp_personal' => $supplier->formatted_no_telp_personal,
                'note' => $note,

                // Data Pelengkap Lainnya
                'user_id' => $supplier->user_id,
                'creator' => [
                    'name' => $supplier->creator?->name,
                    'role' => $supplier->creator?->roles?->first()?->name,
                ],
                'supplier_link' => [
                    'url' => $supplier->supplier_links?->url,
                ],
                
                // Data timestamp spesifik (untuk filter di frontend)
                'submit_1_timestamps' => $status?->submit_1_timestamps,
                'status_1_timestamps' => $status?->status_1_timestamps,
                'status_2_timestamps' => $status?->status_2_timestamps,
            ];
        });

        return Inertia::render('m_supplier/page', [
            'suppliers' => $supplierData,
            'companies' => $user->hasRole('admin')
                ? Perusahaan::select('id', 'nama_perusahaan')->orderBy('nama_perusahaan')->get()
                : [],
            'company' => [
                'id' => session('company_id'),
                'name' => session('company_name'),
                'logo' => session('company_logo'),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }
    
    public function importCsv(Request $request)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.import')) {
            abort(403, 'Unauthorized access. You do not have permission to import supplier CSV.');
        }

        $isMarketing = $user->hasRole('marketing');

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'id_perusahaan' => [$isMarketing ? 'nullable' : 'required', 'integer'],
        ]);

        $idPerusahaan = $isMarketing 
            ? ($user->id_perusahaan ?: session('company_id')) 
            : $request->integer('id_perusahaan');

        if (!$idPerusahaan) {
            return back()->withErrors([
                'id_perusahaan' => 'Perusahaan tujuan tidak ditemukan atau belum disetting.',
            ]);
        }

        $perusahaan = Perusahaan::find($idPerusahaan);

        if (!$perusahaan) {
            return back()->withErrors([
                'id_perusahaan' => 'Perusahaan tujuan tidak ditemukan.',
            ]);
        }

        $file = $request->file('csv_file');
        $path = $file?->getRealPath();

        if (!$path) {
            return back()->withErrors([
                'csv_file' => 'File CSV tidak valid atau tidak bisa dibaca.',
            ]);
        }

        $handle = fopen($path, 'rb');

        if (!$handle) {
            return back()->withErrors([
                'csv_file' => 'Gagal membuka file CSV.',
            ]);
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return back()->withErrors([
                'csv_file' => 'File CSV kosong.',
            ]);
        }

        rewind($handle);

        $delimiter = $this->detectCsvDelimiter($firstLine);
        $headerRow = fgetcsv($handle, 0, $delimiter);

        if ($headerRow === false) {
            fclose($handle);

            return back()->withErrors([
                'csv_file' => 'Header CSV tidak dapat dibaca.',
            ]);
        }

        $header = array_map(fn ($value) => $this->normalizeCsvHeader($value), $headerRow);

        if (count(array_filter($header)) === 0) {
            fclose($handle);

            return back()->withErrors([
                'csv_file' => 'Header CSV kosong atau tidak valid.',
            ]);
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        // Kumpulkan supplier baru yang berhasil disimpan untuk dikirim ke external API
        // setelah commit (agar kegagalan API tidak merusak data import lokal)
        $newSuppliersForApiSync = [];

        DB::connection('tako-supplier')->beginTransaction();
        DB::connection('tako-perusahaan')->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($row === [null] || count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), '');
                }

                if (count($row) > count($header)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: jumlah kolom melebihi header.";
                    continue;
                }

                $rowData = array_combine($header, $row);

                if ($rowData === false) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: data tidak dapat dipetakan ke header.";
                    continue;
                }

                $supplierPayload = $this->mapImportedSupplierRow($rowData);

                if ($supplierPayload['nama_perusahaan'] === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: nama supplier/perusahaan kosong.";
                    continue;
                }

                $existingSupplier = $this->findExistingSupplierForImport(
                    $perusahaan->id,
                    $supplierPayload['nama_perusahaan'],
                    $supplierPayload['no_npwp'],
                    $supplierPayload['no_npwp_16']
                );

                if ($existingSupplier) {
                    $existingSupplier->fill($this->mergeImportedSupplierData($existingSupplier, $supplierPayload));

                    if ($existingSupplier->trashed()) {
                        $existingSupplier->restore();
                    }

                    $existingSupplier->save();
                    $this->ensureSupplierStatusExists($existingSupplier->id, $existingSupplier->id_user ?: $user->id);
                    $updated++;
                    continue;
                }

                $supplier = Supplier::create(array_merge($supplierPayload, [
                    'id_user' => $user->id,
                    'id_perusahaan' => $perusahaan->id,
                ]));

                $this->ensureSupplierStatusExists($supplier->id, $user->id);
                $imported++;

                // Simpan referensi untuk sync API setelah commit
                $newSuppliersForApiSync[] = $supplier;
            }

            DB::connection('tako-perusahaan')->commit();
            DB::connection('tako-supplier')->commit();
        } catch (\Throwable $th) {
            DB::connection('tako-perusahaan')->rollBack();
            DB::connection('tako-supplier')->rollBack();
            fclose($handle);

            return back()->withErrors([
                'csv_file' => 'Terjadi kesalahan saat import CSV: ' . $th->getMessage(),
            ]);
        }

        fclose($handle);

        // Kirim setiap supplier baru ke external API (di luar transaction).
        // Kegagalan API tidak merusak data import yang sudah tersimpan.
        foreach ($newSuppliersForApiSync as $syncedSupplier) {
            try {
                $this->sendSupplierToExternalApi($syncedSupplier, $user);

                Log::info('[ImportCSV] Berhasil sync supplier ke external API.', [
                    'supplier_id'   => $syncedSupplier->id,
                    'nama_perusahaan' => $syncedSupplier->nama_perusahaan,
                ]);
            } catch (\Throwable $apiEx) {
                Log::error('[ImportCSV] Gagal sync supplier ke external API.', [
                    'supplier_id'   => $syncedSupplier->id,
                    'nama_perusahaan' => $syncedSupplier->nama_perusahaan,
                    'error'         => $apiEx->getMessage(),
                ]);
            }
        }

        $successMessage = "Import supplier selesai. {$imported} data baru, {$updated} data diperbarui.";
        $errorMessage = null;

        if ($skipped > 0) {
            $errorMessage = "Ada {$skipped} baris yang dilewati. " . Str::limit(implode(' | ', $errors), 500);
        }

        return redirect()
            ->route('supplier.index')
            ->with('success', $successMessage)
            ->with('error', $errorMessage);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.create')) {
            throw UnauthorizedException::forPermissions(['supplier.create']);
        }

        $companies = collect();

        if ($user->hasRole('admin')) {
            $companies = Perusahaan::select(
                'id',
                'nama_perusahaan',
                'is_npwp',
                'is_nib',
                'is_sptkp',
                'is_ktp'
            )
            ->get();
        } elseif ($user->hasRole(['manager', 'direktur'])) {
            $companies = $user->companies()
                ->select(
                    'perusahaan.id',
                    'perusahaan.nama_perusahaan',
                    'perusahaan.is_npwp',
                    'perusahaan.is_nib',
                    'perusahaan.is_sptkp',
                    'perusahaan.is_ktp'
                )
                ->get();
        } elseif (!empty($user->id_perusahaan)) {
            $companies = Perusahaan::where('id', $user->id_perusahaan)
                ->select(
                    'id',
                    'nama_perusahaan',
                    'is_npwp',
                    'is_nib',
                    'is_sptkp',
                    'is_ktp'
                )
                ->get();
        }

        $defaultCompany = $companies->first();

        return Inertia::render('m_supplier/table/add-data-form', [
            'companies' => $companies->map(fn ($company) => [
                'id' => $company->id,
                'nama_perusahaan' => $company->nama_perusahaan,
                'is_npwp' => (bool) $company->is_npwp,
                'is_nib' => (bool) $company->is_nib,
                'is_sptkp' => (bool) $company->is_sptkp,
                'is_ktp' => (bool) $company->is_ktp,
            ])->values(),

            'attachmentRules' => [
                'is_npwp' => (bool) ($defaultCompany?->is_npwp ?? true),
                'is_nib' => (bool) ($defaultCompany?->is_nib ?? true),
                'is_sptkp' => (bool) ($defaultCompany?->is_sptkp ?? false),
                'is_ktp' => (bool) ($defaultCompany?->is_ktp ?? true),
            ],

            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Share the form to supplier
     */
    public function share()
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.create')) {
            throw UnauthorizedException::forPermissions(['supplier.create']);
        }

        return Inertia::render('m_supplier/table/generate-data-form', [
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.create')) {
            throw UnauthorizedException::forPermissions(['supplier.create']);
        }

        $roles = $user->getRoleNames();

        if ($roles->contains('marketing')) {
            $idPerusahaan = $user->id_perusahaan;
        } elseif ($roles->contains('manager') || $roles->contains('direktur') || $roles->contains('admin')) {
            $idPerusahaan = $request->id_perusahaan;
        } else {
            $idPerusahaan = $user->id_perusahaan;
        }

        if (!$idPerusahaan) {
            return redirect()
                ->back()
                ->withErrors(['id_perusahaan' => 'Perusahaan wajib dipilih.']);
        }

        $perusahaan = Perusahaan::find($idPerusahaan);

        if (!$perusahaan) {
            return redirect()
                ->back()
                ->withErrors(['id_perusahaan' => 'Perusahaan tidak ditemukan.']);
        }

        $validated = $request->validate([
            'supplier_category' => 'required|string',
            'kategori_usaha' => 'required|string',
            'nama_perusahaan' => 'required|string',
            'bentuk_badan_usaha' => 'required|string',
            'jenis_perusahaan' => 'required|string|in:Perusahaan Dalam Negeri,Perusahaan Luar Negeri',
            'alamat_lengkap' => 'required|string',
            'kota' => 'required|string',
            'no_telp' => 'nullable',
            'no_fax' => 'nullable|string',
            'alamat_penagihan' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|string',
            'top' => 'nullable|string',
            'status_perpajakan' => 'nullable|string',
            'no_npwp' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'no_npwp_16' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'nib' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',
            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable',
            'email_personal' => 'nullable|email',
            'keterangan_reject' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'approved_1_by' => 'nullable|integer',
            'approved_2_by' => 'nullable|integer',
            'rejected_1_by' => 'nullable|integer',
            'rejected_2_by' => 'nullable|integer',
            'keterangan' => 'nullable|string',
            'tgl_approval_1' => 'nullable|date',
            'tgl_approval_2' => 'nullable|date',
            'tgl_supplier' => 'nullable|date',

            'attachments' => 'nullable|array',
            'attachments.*.nama_file' => 'required_with:attachments|string',
            'attachments.*.path' => 'required_with:attachments|string',
            'attachments.*.type' => 'required_with:attachments|in:npwp,sppkp,ktp,nib',
        ]);

        $attachmentTypes = collect($validated['attachments'] ?? [])
            ->pluck('type')
            ->toArray();

        $isSupplierPerorangan = $request->bentuk_badan_usaha === 'Supplier Perorangan';
        $isLuarNegeri = $request->jenis_perusahaan === 'Perusahaan Luar Negeri';

        if (!$isLuarNegeri && $perusahaan->is_npwp && !in_array('npwp', $attachmentTypes, true)) {
            return redirect()
                ->back()
                ->withErrors(['attachments' => 'Dokumen NPWP wajib diunggah.']);
        }

        if (!$isLuarNegeri && $perusahaan->is_nib && !$isSupplierPerorangan && !in_array('nib', $attachmentTypes, true)) {
            return redirect()
                ->back()
                ->withErrors(['attachments' => 'Dokumen NIB wajib diunggah.']);
        }

        if ($perusahaan->is_sptkp && !in_array('sppkp', $attachmentTypes, true)) {
            return redirect()
                ->back()
                ->withErrors(['attachments' => 'Dokumen SPTKP wajib diunggah.']);
        }

        if ($perusahaan->is_ktp && !in_array('ktp', $attachmentTypes, true)) {
            return redirect()
                ->back()
                ->withErrors(['attachments' => 'Dokumen KTP wajib diunggah.']);
        }

        try {
            DB::beginTransaction();

            $supplier = Supplier::create(array_merge($validated, [
                'id_user' => $user->id,
                'id_perusahaan' => $idPerusahaan,
            ]));

            if (!empty($validated['attachments'])) {
                foreach ($validated['attachments'] as $attachment) {
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        SupplierAttach::create([
                            'supplier_id' => $supplier->id,
                            'nama_file' => $attachment['nama_file'],
                            'path' => $attachment['path'],
                            'type' => $attachment['type'],
                        ]);
                    }
                }
            }

            DB::connection('tako-perusahaan')->table('suppliers_statuses')->insert([
                'id_Supplier' => $supplier->id,
                'id_user' => $user->id,
                'submit_1_timestamps' => null,
                'status_1_by' => null,
                'status_1_timestamps' => null,
                'status_1_keterangan' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->sendSupplierToExternalApi($supplier, $user);

            return Inertia::location(route('supplier.show', $supplier->id));
        } catch (\Throwable $th) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withErrors(['error' => 'Terjadi kesalahan: ' . $th->getMessage()]);
        }
    }

    public function storePublic(Request $request)
    {
        DB::beginTransaction();

        try {
            $userId = $request->input('user_id');

            $link = SupplierLink::on('tako-perusahaan')
                ->where('id_user', $userId)
                ->whereNull('id_supplier')
                ->where('is_filled', false)
                ->latest('id_link')
                ->first();

            if (!$link) {
                throw new \Exception('Link tidak ditemukan atau sudah digunakan.');
            }

            $id_perusahaan = $link->id_perusahaan;

            $perusahaan = Perusahaan::find($id_perusahaan);

            if (!$perusahaan) {
                throw new \Exception('Perusahaan tidak ditemukan.');
            }

            $validated = $request->validate([
                'supplier_category' => 'required|string',
            'kategori_usaha' => 'required|string',
                'nama_perusahaan' => 'required|string',
                'bentuk_badan_usaha' => 'required|string',
                'jenis_perusahaan' => 'required|string|in:Perusahaan Dalam Negeri,Perusahaan Luar Negeri',
                'alamat_lengkap' => 'required|string',
                'kota' => 'required|string',
                'no_telp' => 'nullable',
                'no_fax' => 'nullable|string',
                'alamat_penagihan' => 'required|string',
                'email' => 'required|email',
                'website' => 'nullable|string',
                'top' => 'nullable|string',
                'status_perpajakan' => 'nullable|string',
                'no_npwp' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
                'no_npwp_16' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
                'nib' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
                'nama_pj' => 'nullable|string',
                'no_ktp_pj' => 'nullable|string',
                'no_telp_pj' => 'nullable|string',
                'nama_personal' => 'nullable|string',
                'jabatan_personal' => 'nullable|string',
                'no_telp_personal' => 'nullable',
                'email_personal' => 'nullable|email',
                'keterangan_reject' => 'nullable|string',
                'user_id' => 'required|exists:users,id',
                'approved_1_by' => 'nullable|integer',
                'approved_2_by' => 'nullable|integer',
                'rejected_1_by' => 'nullable|integer',
                'rejected_2_by' => 'nullable|integer',
                'keterangan' => 'nullable|string',
                'tgl_approval_1' => 'nullable|date',
                'tgl_approval_2' => 'nullable|date',
                'tgl_supplier' => 'nullable|date',

                'attachments' => 'nullable|array',
                'attachments.*.nama_file' => 'required_with:attachments|string',
                'attachments.*.path' => 'required_with:attachments|string',
                'attachments.*.type' => 'required_with:attachments|in:npwp,sppkp,ktp,nib',
            ]);

            $attachmentTypes = collect($validated['attachments'] ?? [])
                ->pluck('type')
                ->toArray();

            $isSupplierPerorangan = $request->bentuk_badan_usaha === 'Supplier Perorangan';
            $isLuarNegeri = $request->jenis_perusahaan === 'Perusahaan Luar Negeri';

            if (!$isLuarNegeri && $perusahaan->is_npwp && !in_array('npwp', $attachmentTypes, true)) {
                return response()->json([
                    'error' => 'Dokumen NPWP wajib diunggah.',
                ], 422);
            }

            if (!$isLuarNegeri && $perusahaan->is_nib && !$isSupplierPerorangan && !in_array('nib', $attachmentTypes, true)) {
                return response()->json([
                    'error' => 'Dokumen NIB wajib diunggah.',
                ], 422);
            }

            if ($perusahaan->is_sptkp && !in_array('sppkp', $attachmentTypes, true)) {
                return response()->json([
                    'error' => 'Dokumen SPTKP wajib diunggah.',
                ], 422);
            }

            if ($perusahaan->is_ktp && !in_array('ktp', $attachmentTypes, true)) {
                return response()->json([
                    'error' => 'Dokumen KTP wajib diunggah.',
                ], 422);
            }

            $supplier = Supplier::create(array_merge($validated, [
                'id_user' => $userId,
                'id_perusahaan' => $id_perusahaan,
            ]));

            if (!empty($validated['attachments'])) {
                foreach ($validated['attachments'] as $attachment) {
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        SupplierAttach::create([
                            'supplier_id' => $supplier->id,
                            'nama_file' => $attachment['nama_file'],
                            'path' => $attachment['path'],
                            'type' => $attachment['type'],
                        ]);
                    }
                }
            }

            DB::connection('tako-perusahaan')->table('suppliers_statuses')->insert([
                'id_Supplier' => $supplier->id,
                'id_user' => $userId,
                'submit_1_timestamps' => null,
                'status_1_by' => null,
                'status_1_timestamps' => null,
                'status_1_keterangan' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $link->update([
                'id_supplier' => $supplier->id,
                'is_filled' => true,
                'filled_at' => now(),
            ]);

            DB::commit();

            $linkUser = User::find($userId);

            if ($linkUser) {
                $isMarketing = $linkUser->hasRole('marketing');

                $this->sendSupplierToExternalApi(
                    $supplier,
                    $linkUser,
                    $isMarketing ? null : ''
                );
            } else {
                Log::warning('User link tidak ditemukan saat kirim supplier public ke external API.', [
                    'supplier_id' => $supplier->id,
                    'user_id' => $userId,
                ]);
            }

            return response()->json([
                'message' => 'Data Anda berhasil dibuat!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'error' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function upload(Request $request)
    {
        $file = $request->file('pdf') ?? $request->file('file');

        if (!$file) {
            return response()->json(['error' => 'File tidak ditemukan'], 400);
        }

        $validator = Validator::make(
            ['file' => $file],
            [
                'file' => [
                    'required',
                    'file',
                    'max:5120',
                    'mimes:pdf,jpg,jpeg,png,webp',
                ],
            ],
            [
                'file.mimes' => 'File harus berformat PDF, JPG, JPEG, PNG, atau WEBP.',
                'file.max' => 'Ukuran file maksimal 5 MB.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('file'),
            ], 422);
        }

        $order = str_pad((int) $request->input('order'), 3, '0', STR_PAD_LEFT);
        $npwp = preg_replace('/[^0-9]/', '', $request->input('npwp_number'));
        $type = strtolower($request->input('type'));

        $ext = strtolower($file->getClientOriginalExtension());
        $uniqueId = uniqid();
        $filename = "{$npwp}-{$order}-{$type}-{$uniqueId}.{$ext}";

        $disk = Storage::disk('suppliers_external');
        $tempDir = 'temp';

        if (!$disk->exists($tempDir)) {
            $disk->makeDirectory($tempDir);
        }

        $tempRel = "{$tempDir}/{$filename}";

        $disk->put($tempRel, file_get_contents($file->getRealPath()));

        return response()->json([
            'status' => 'success',
            'path' => $tempRel,
            'nama_file' => $filename,
            'is_temp' => true,
            'info' => 'File uploaded to temp uncompressed',
        ]);
    }

    public function processAttachment(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'nama_file' => 'required|string',
            'id_perusahaan' => 'nullable|integer',
            'mode' => 'nullable|string',
            'role' => 'nullable|string',
            'type' => 'nullable|string',
            'npwp_number' => 'nullable|string',
            'supplier_id' => 'nullable|integer', // TAMBAHAN: Butuh ID Supplier untuk cek urutan file terakhir
        ]);

        $tempPath = $request->path;
        $originalName = $request->nama_file;
        $mode = $request->mode ?? 'medium';
        $idPerusahaan = $request->id_perusahaan;
        $role = strtolower($request->role ?? 'marketing');
        if ($role === 'user') {
            $role = 'marketing';
        }
        $supplierId = $request->supplier_id;
        $incrementOrder = (int)($request->increment_order ?? 1);

        $nextOrder = 1;

        // 1. Setup Disk & Slug
        $disk = Storage::disk('suppliers_external');

        $companySlug = 'general';
        if ($idPerusahaan) {
            $perusahaan = Perusahaan::find($idPerusahaan);
            if ($perusahaan) {
                $companySlug = Str::slug($perusahaan->nama_perusahaan);
            }
        }

        if (!$disk->exists($tempPath)) {
            return response()->json(['error' => 'File temp tidak ditemukan'], 404);
        }

        if ($supplierId) {
            $lastFromAttach = SupplierAttach::where('supplier_id', $supplierId)
                ->get()
                ->map(fn($r) => intval(explode('-', $r->nama_file)[1] ?? 0))
                ->max() ?? 0;

            // ... (logika cek status file sama) ...
            $status = \App\Models\Suppliers_Status::where('id_Supplier', $supplierId)->first();
            $statusFields = [
                'submit_1_nama_file',
                'status_1_nama_file',
                'status_2_nama_file',
                'submit_3_nama_file',
                'status_4_nama_file'
            ];
            $lastFromStatus = 0;
            if ($status) {
                $lastFromStatus = collect($statusFields)
                    ->map(function ($field) use ($status) {
                        $filename = $status->$field;
                        if (!$filename) return 0;

                        $parts = explode('-', $filename);
                        if (preg_match('/\-(\d{3})\-/', $filename, $matches)) {
                            return intval($matches[1]);
                        }

                        // Fallback ke logic explode jika simple
                        return intval($parts[1] ?? 0);
                    })
                    ->max() ?? 0;
            }

            $maxDbOrder = max($lastFromAttach, $lastFromStatus);

            $nextOrder = $maxDbOrder + $incrementOrder;
        } else {
            $nextOrder = $incrementOrder;
        }
        $orderString = str_pad($nextOrder, 3, '0', STR_PAD_LEFT);

        $npwp = preg_replace('/[^0-9]/', '', $request->npwp_number) ?: '0000000000000000';

        $docType = $request->type ? strtolower($request->type) : 'document';

        if ($docType === 'lampiran_marketing') $docType = 'marketing_review';
        if ($docType === 'lampiran_auditor') $docType = 'audit_review';
        if ($docType === 'lampiran_review_general') {
            $docType = match ($role) {
                'manager'  => 'manager_review',
                'direktur' => 'director_review',
                'lawyer'   => 'lawyer_review',
                'auditor'  => 'audit_review',
                default    => 'document'
            };
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $isPdf = $ext === 'pdf';
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);

        if (!$isPdf && !$isImage) {
            return response()->json([
                'error' => 'Format file tidak didukung.',
            ], 422);
        }

        // Jika image, hasil kompres disimpan sebagai JPG
        $finalExt = $isImage ? 'jpg' : $ext;

        $newFileName = "{$npwp}-{$orderString}-{$docType}.{$finalExt}";

        $subFolder = ($role === 'marketing' || $role === 'user') ? 'attachment' : 'suppliers';
        if (in_array($docType, ['npwp', 'nib', 'sppkp', 'ktp'])) {
            $subFolder = 'attachment';
        }

        $targetDir = "{$companySlug}/{$subFolder}";
        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);
        }

        $finalRelPath = "{$targetDir}/{$newFileName}";

        $success = false;

        if ($isPdf) {
            $localInputName = 'gs_in_' . uniqid() . '.pdf';
            $localOutputName = 'gs_out_' . uniqid() . '.pdf';

            Storage::disk('local')->put("gs_processing/{$localInputName}", $disk->get($tempPath));

            $localInputPath = Storage::disk('local')->path("gs_processing/{$localInputName}");
            $localOutputPath = Storage::disk('local')->path("gs_processing/{$localOutputName}");

            $compressResult = $this->runGhostscript($localInputPath, $localOutputPath, $mode);

            if ($compressResult && file_exists($localOutputPath)) {
                $disk->put($finalRelPath, file_get_contents($localOutputPath));
                $success = true;
                @unlink($localOutputPath);
            } else {
                Log::warning("Ghostscript Gagal. Menggunakan file asli.");
            }

            @unlink($localInputPath);
        }

        if ($isImage) {
            $localInputName = 'img_in_' . uniqid() . '.' . $ext;
            $localOutputName = 'img_out_' . uniqid() . '.jpg';

            Storage::disk('local')->put("image_processing/{$localInputName}", $disk->get($tempPath));

            $localInputPath = Storage::disk('local')->path("image_processing/{$localInputName}");
            $localOutputPath = Storage::disk('local')->path("image_processing/{$localOutputName}");

            try {
                $this->compressImage($localInputPath, $localOutputPath, $mode);

                if (file_exists($localOutputPath) && filesize($localOutputPath) > 0) {
                    $disk->put($finalRelPath, file_get_contents($localOutputPath));
                    $success = true;
                }
            } catch (\Throwable $e) {
                Log::error('Gagal kompres image: ' . $e->getMessage());
            }

            @unlink($localInputPath);
            @unlink($localOutputPath);
        }

        if (!$success) {
            if ($disk->exists($finalRelPath)) $disk->delete($finalRelPath);
            $disk->move($tempPath, $finalRelPath);
        } else {
            if ($disk->exists($tempPath)) $disk->delete($tempPath);
        }

        return response()->json([
            'status' => 'success',
            'final_path' => $finalRelPath,
            'nama_file' => $newFileName,
            'compressed' => $success
        ]);
    }

    private function runGhostscript($inputPath, $outputPath, $mode)
    {
        $settings = [
            'small'  => ['-dPDFSETTINGS=/ebook', '-dColorImageResolution=150', '-dGrayImageResolution=150', '-dMonoImageResolution=150'],
            'medium' => ['-dPDFSETTINGS=/ebook', '-dColorImageResolution=200', '-dGrayImageResolution=200', '-dMonoImageResolution=200'],
            'high'   => ['-dPDFSETTINGS=/printer', '-dColorImageResolution=300', '-dGrayImageResolution=300', '-dMonoImageResolution=300'],
        ];
        $config = $settings[$mode] ?? $settings['medium'];

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $gsExe = $isWindows ? 'C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe' : '/usr/bin/gs';

        if ($isWindows) {
            $inputPath = str_replace('/', '\\', $inputPath);
            $outputPath = str_replace('/', '\\', $outputPath);
        }

        $cmd = array_merge([
            $gsExe,
            '-q',
            '-dSAFER',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-o',
            $outputPath,
            $inputPath
        ], $config);

        try {
            $process = new Process($cmd);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Ghostscript Error Output: ' . $process->getErrorOutput());
                return false;
            }

            return file_exists($outputPath) && filesize($outputPath) > 0;
        } catch (\Exception $e) {
            Log::error("GS Process Exception: " . $e->getMessage());
            return false;
        }
    }

    private function compressImage(string $inputPath, string $outputPath, string $mode = 'medium'): void
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('PHP GD extension belum aktif.');
        }

        if (!file_exists($inputPath)) {
            throw new \Exception('File input image tidak ditemukan.');
        }

        $beforeSize = filesize($inputPath);

        $quality = match ($mode) {
            'small' => 55,
            'medium' => 70,
            'high' => 85,
            default => 70,
        };

        $maxWidth = match ($mode) {
            'small' => 1200,
            'medium' => 1600,
            'high' => 2200,
            default => 1600,
        };

        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));

        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => imagecreatefromjpeg($inputPath),
            'png' => imagecreatefrompng($inputPath),
            'webp' => imagecreatefromwebp($inputPath),
            default => false,
        };

        if (!$sourceImage) {
            throw new \Exception('Format image tidak didukung atau file image rusak.');
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        if ($originalWidth <= 0 || $originalHeight <= 0) {
            imagedestroy($sourceImage);
            throw new \Exception('Ukuran image tidak valid.');
        }

        if ($originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round(($originalHeight / $originalWidth) * $newWidth);
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Background putih agar PNG transparan aman saat dikonversi ke JPG
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

        imagecopyresampled(
            $newImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        $saved = imagejpeg($newImage, $outputPath, $quality);

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        if (!$saved || !file_exists($outputPath) || filesize($outputPath) <= 0) {
            throw new \Exception('Gagal menyimpan hasil kompres image.');
        }

        $afterSize = filesize($outputPath);

        Log::info('Image compression result', [
            'input_path' => $inputPath,
            'output_path' => $outputPath,
            'mode' => $mode,
            'quality' => $quality,
            'max_width' => $maxWidth,
            'original_width' => $originalWidth,
            'original_height' => $originalHeight,
            'new_width' => $newWidth,
            'new_height' => $newHeight,
            'before_size_kb' => round($beforeSize / 1024, 2),
            'after_size_kb' => round($afterSize / 1024, 2),
            'saved_kb' => round(($beforeSize - $afterSize) / 1024, 2),
            'saved_percent' => $beforeSize > 0
                ? round((($beforeSize - $afterSize) / $beforeSize) * 100, 2)
                : 0,
            'compressed_success' => $afterSize > 0 && $afterSize < $beforeSize,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $user = auth('web')->user();

        $hasGlobalAccess = $user->can('supplier.approve.auditor') || ($user->can('supplier.approve.lawyer') && empty($user->id_perusahaan));

        if (!$hasGlobalAccess && !$user->can('supplier.view')) {
            throw UnauthorizedException::forPermissions(['supplier.view']);
        }

        if ($hasGlobalAccess) {
            $supplier->load('attachments');

            return Inertia::render('m_supplier/table/view-data-form', [
                'supplier' => $this->serializeSupplierForFrontend($supplier),
                'attachments' => $supplier->attachments,
            ]);
        }

        if (!$user->hasRole('admin')) {
            $userCompanyIds = $user->companies()->pluck('perusahaan.id')->toArray();

            if (!empty($user->id_perusahaan)) {
                $userCompanyIds[] = $user->id_perusahaan;
            }
            if (!in_array($supplier->id_perusahaan, $userCompanyIds)) {
                abort(403, 'Anda tidak memiliki akses ke data supplier ini.');
            }
        }

        $supplier->load('attachments');

        return Inertia::render('m_supplier/table/view-data-form', [
            'supplier' => $this->serializeSupplierForFrontend($supplier),
            'attachments' => $supplier->attachments,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.update')) {
            abort(403, 'Unauthorized action.');
        }

        $supplier->load('attachments');

        if ($user->hasRole('admin')) {
            $companies = Perusahaan::select(
                'id',
                'nama_perusahaan',
                'is_npwp',
                'is_nib',
                'is_sptkp',
                'is_ktp'
            )->get();
        } else {
            $userCompanyIds = $user->companies()->pluck('perusahaan.id')->toArray();

            if (!empty($user->id_perusahaan)) {
                $userCompanyIds[] = $user->id_perusahaan;
            }

            if (!in_array($supplier->id_perusahaan, $userCompanyIds)) {
                abort(403, 'Anda tidak memiliki akses ke data supplier ini.');
            }

            $companies = $user->companies()
                ->select(
                    'perusahaan.id',
                    'perusahaan.nama_perusahaan',
                    'perusahaan.is_npwp',
                    'perusahaan.is_nib',
                    'perusahaan.is_sptkp',
                    'perusahaan.is_ktp'
                )
                ->get();
        }

        $company = Perusahaan::find($supplier->id_perusahaan);

        return Inertia::render('m_supplier/table/edit-data-form', [
            'supplier' => $this->serializeSupplierForFrontend($supplier, true),

            'attachmentRules' => [
                'is_npwp' => (bool) ($company?->is_npwp ?? true),
                'is_nib' => (bool) ($company?->is_nib ?? true),
                'is_sptkp' => (bool) ($company?->is_sptkp ?? false),
                'is_ktp' => (bool) ($company?->is_ktp ?? true),
            ],

            'companies' => $companies->map(fn ($company) => [
                'id' => $company->id,
                'nama_perusahaan' => $company->nama_perusahaan,
                'is_npwp' => (bool) $company->is_npwp,
                'is_nib' => (bool) $company->is_nib,
                'is_sptkp' => (bool) $company->is_sptkp,
                'is_ktp' => (bool) $company->is_ktp,
            ])->values(),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.update')) {
            abort(403, 'Unauthorized action.');
        }

        $createdDate = \Carbon\Carbon::parse($supplier->created_at)->toDateString();
        $today = now()->toDateString();

        $canEditToday = $createdDate === $today;

        $validated = $request->validate([
            'id_perusahaan' => [
                'required',
                Rule::exists((new Perusahaan)->getTable(), 'id'),
            ],
            'supplier_category' => 'required|string',
            'kategori_usaha' => 'required|string',
            'nama_perusahaan' => 'required|string',
            'bentuk_badan_usaha' => 'required|string',
            'jenis_perusahaan' => 'required|string|in:Perusahaan Dalam Negeri,Perusahaan Luar Negeri',
            'alamat_lengkap' => 'required|string',
            'kota' => 'required|string',
            'no_telp' => 'nullable',
            'no_fax' => 'nullable|string',
            'alamat_penagihan' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|string',
            'top' => 'nullable|string',
            'status_perpajakan' => 'nullable|string',
            'no_npwp' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'no_npwp_16' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'nib' => 'required_if:jenis_perusahaan,Perusahaan Dalam Negeri|nullable|string',
            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',
            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable',
            'email_personal' => 'nullable|email',
            'keterangan_reject' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'approved_1_by' => 'nullable|integer',
            'approved_2_by' => 'nullable|integer',
            'rejected_1_by' => 'nullable|integer',
            'rejected_2_by' => 'nullable|integer',
            'keterangan' => 'nullable|string',
            'tgl_approval_1' => 'nullable|date',
            'tgl_approval_2' => 'nullable|date',
            'tgl_supplier' => 'nullable|date',

            'attachments' => 'required|array',
            'attachments.*.nama_file' => 'required|string',
            'attachments.*.path' => 'required|string',
            'attachments.*.type' => 'required|in:npwp,sppkp,ktp,nib',
        ]);

        try {
            DB::beginTransaction();

            $supplier->update($validated);
            $roles = $user->getRoleNames();

            if (isset($validated['attachments'])) {
                SupplierAttach::where('supplier_id', $supplier->id)->delete();

                foreach ($validated['attachments'] as $attachment) {
                    // Pastikan path bukan blob local (hanya defensive check)
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        SupplierAttach::create([
                            'supplier_id' => $supplier->id,
                            'nama_file'   => $attachment['nama_file'],
                            'path'        => $attachment['path'], // Path ini SUDAH FINAL dari proses frontend
                            'type'        => $attachment['type'],
                        ]);
                    }
                }
            }

            DB::commit();

            try {
                $this->sendSupplierToExternalApi($supplier, $user);
            } catch (\Throwable $apiEx) {
                Log::error('[Update] Gagal sync supplier ke external API.', [
                    'supplier_id'   => $supplier->id,
                    'nama_perusahaan' => $supplier->nama_perusahaan,
                    'error'         => $apiEx->getMessage(),
                ]);
            }

            return redirect()->route('supplier.index')->with('success', 'Data Supplier berhasil diperbarui!');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan: ' . $th->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $user = auth('web')->user();

        if (!$user->can('supplier.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $supplier->delete();

            DB::commit();

            return redirect()->route('supplier.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('supplier.index')
                ->with('error', 'Gagal menghapus Data Supplier: ' . $e->getMessage());
        }
    }

    public function generatePdf($id)
    {
        Log::info("📄 Mulai generate PDF untuk supplier ID: {$id}");

        $supplier = Supplier::with(['attachments', 'perusahaan'])->findOrFail($id);
        $user = auth('web')->user();

        if (!$user->can('supplier.pdf')) {
            abort(403, 'Unauthorized action.');
        }

        $tempDir = storage_path("app/temp");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 2. Generate PDF Utama (Cover/Data Supplier)
        $mainPdfPath = "{$tempDir}/supplier_{$supplier->id}_main.pdf";
        $mainPdf = Pdf::loadView('pdf.supplier', [
            'supplier' => $supplier,
            'generated_by' => $user?->name ?? 'Guest',
        ])->setPaper('a4');
        file_put_contents($mainPdfPath, $mainPdf->output());

        // 3. Proses Attachment
        $attachmentPdfPaths = [];

        $externalRoot = '/mnt/Supplier_Registration';

        if ($supplier->attachments && count($supplier->attachments) > 0) {
            foreach ($supplier->attachments as $attachment) {

                // Filter: Hanya ambil dokumen penting (NPWP, NIB, KTP, dll)
                if (!in_array($attachment->type, ['npwp', 'nib', 'ktp'])) continue;

                // --- LOGIC PENGGABUNGAN PATH ---

                // 1. Ambil path dari DB: "pt-alpha/attachment/313...-003-ktp.pdf"
                $dbPath = $attachment->path;

                // 2. Bersihkan path (Jaga-jaga jika di DB tersimpan "storage/pt-alpha/...")
                // Kita hapus kata 'storage/' atau '/storage/' agar mendapatkan relative path yang murni
                $cleanRelativePath = ltrim(str_replace(['/storage/', 'storage/'], '', $dbPath), '/');

                // 3. Gabungkan Root Eksternal + Relative Path
                // Hasil: "/mnt/Supplier_Registration/pt-alpha/attachment/313...-003-ktp.pdf"
                $fullFilePath = "{$externalRoot}/{$cleanRelativePath}";

                // 4. Validasi Keberadaan File di Linux
                if (!file_exists($fullFilePath)) {
                    Log::warning("⚠️ File fisik tidak ditemukan di: {$fullFilePath}");
                    continue; // Skip jika file tidak ada
                }

                // --- PROSES KONVERSI (PDF/GAMBAR) ---

                if (Str::endsWith(strtolower($fullFilePath), '.pdf')) {
                    // Jika sudah PDF, langsung masukkan antrian merge
                    $attachmentPdfPaths[] = $fullFilePath;
                } else {
                    // Jika Gambar (JPG/PNG), Convert ke PDF dulu menggunakan View Wrapper
                    try {
                        $convertedPdfPath = "{$tempDir}/converted_{$attachment->type}_{$attachment->id}.pdf";

                        $html = view('pdf.attachment-wrapper', [
                            'title' => strtoupper($attachment->type),
                            'filePath' => $fullFilePath, // DomPDF support absolute path linux
                            'extension' => pathinfo($fullFilePath, PATHINFO_EXTENSION),
                        ])->render();

                        $converted = Pdf::loadHTML($html)->setPaper('a4');
                        file_put_contents($convertedPdfPath, $converted->output());

                        $attachmentPdfPaths[] = $convertedPdfPath;
                    } catch (\Exception $e) {
                        Log::error("Gagal convert gambar attachment ID {$attachment->id}: " . $e->getMessage());
                    }
                }
            }
        }

        // 4. Merge PDF (Main + Attachments)
        $mergedPath = "{$tempDir}/supplier_{$supplier->id}_full.pdf";
        $finalPath = $mainPdfPath; // Default fallback (hanya cover jika merge gagal)

        try {
            if (count($attachmentPdfPaths) > 0) {
                // Gabungkan Main PDF dengan semua Attachment
                $filesToMerge = array_merge([$mainPdfPath], $attachmentPdfPaths);

                // Panggil fungsi Ghostscript Helper
                $this->mergePdfsWithGhostscript($filesToMerge, $mergedPath);

                if (file_exists($mergedPath) && filesize($mergedPath) > 1000) {
                    $finalPath = $mergedPath;
                } else {
                    throw new \Exception('File hasil merge corrupt atau kosong.');
                }
            }
        } catch (\Throwable $e) {
            Log::error("⚠️ Merge gagal (Ghostscript Error), mengirim PDF utama saja. Error: " . $e->getMessage());
        }

        Log::info("✅ Generate PDF Selesai. File: {$finalPath}");

        $namaPerusahaan = preg_replace('/[^A-Za-z0-9_\- ]/', '', $supplier->nama_perusahaan);
        $fileName = "Data Supplier {$namaPerusahaan}.pdf";

        return response()->download($finalPath, $fileName, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    private function mergePdfsWithGhostscript(array $inputPaths, string $outputPath)
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $gsCmd = $isWindows ? 'gswin64c' : 'gs';

        $inputFiles = implode(' ', array_map(function ($path) {
            return '"' . str_replace('\\', '/', $path) . '"';
        }, $inputPaths));

        $outputFile = '"' . str_replace('\\', '/', $outputPath) . '"';
        $cmd = "{$gsCmd} -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile={$outputFile} {$inputFiles}";

        exec($cmd . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Ghostscript gagal menggabungkan PDF. Kode: {$returnVar}");
        }
    }

    public function showPublicForm($token)
    {
        $link = SupplierLink::where('token', $token)->first();

        if (!$link) {
            abort(404, 'Link tidak valid atau sudah tidak tersedia.');
        }

        if ($link->is_filled) {
            return inertia('m_supplier/table/filled-already');
        }

        $perusahaan = DB::connection('tako-perusahaan')
            ->table('perusahaan')
            ->where('id', $link->id_perusahaan)
            ->first();

        $domain = null;

        if ($perusahaan?->id_domain) {
            $domain = DB::table('domains')
                ->where('id', $perusahaan->id_domain)
                ->first();
        }

        Log::info('Link detail', [
            'id_user' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
            'token' => $token,
            'company' => $perusahaan,
            'domain' => $domain,
        ]);

        return inertia('m_supplier/table/public-data-form', [
            'supplier_name' => $link->nama_supplier,
            'supplier' => null,
            'token' => $token,
            'user_id' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
            'isFilled' => $link->is_filled,

            'attachmentRules' => [
                'is_npwp' => (bool) ($perusahaan?->is_npwp ?? true),
                'is_nib' => (bool) ($perusahaan?->is_nib ?? true),
                'is_sptkp' => (bool) ($perusahaan?->is_sptkp ?? false),
                'is_ktp' => (bool) ($perusahaan?->is_ktp ?? true),
            ],

            'company' => [
                'id' => $perusahaan?->id,
                'name' => $perusahaan?->nama_perusahaan ?? '-',
                'logo' => $domain?->path_company_logo ?? null,
                'domain' => $domain?->domain ?? null,
            ],
        ]);
    }

    public function submitPublicForm(Request $request, $token)
    {
        $link = SupplierLink::where('token', $token)->first();

        if (!$link) {
            abort(404, 'Token tidak ditemukan');
        }

        Log::info('Link detail testing', [
            'id_perusahaan' => $link->id_perusahaan,
        ]);

        $validated = $request->validate([
            'supplier_category' => 'required|string',
            'kategori_usaha' => 'required|string',
            'nama_perusahaan' => 'required|string',
            'alamat_lengkap' => 'required|string',
            'bentuk_badan_usaha' => 'required|string',
            'kota' => 'required|string',
            'alamat_penagihan' => 'required|string',
            'email' => 'required|email',
            'top' => 'required|string',
            'status_perpajakan' => 'required|string',
            'nama_pj' => 'required|string',
            'no_ktp_pj' => 'required|string',
            'nama_personal' => 'required|string',
            'jabatan_personal' => 'required|string',
            'email_personal' => 'required|email',
        ]);

        $supplier = Supplier::create(array_merge($validated, [
            'id_user' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
        ]));

        return redirect('/')->with('success', 'Data berhasil dikirim.');
    }

    public function checkNpwp(Request $request)
    {
        $request->validate([
            'no_npwp' => 'required|string',
            'no_npwp_16' => 'nullable|string',
        ]);

        $suppliers = Supplier::with('perusahaan')
            ->where(function ($query) use ($request) {
                $query->where('no_npwp', $request->no_npwp)
                    ->when($request->no_npwp_16, function ($q) use ($request) {
                        $q->orWhere('no_npwp_16', $request->no_npwp_16);
                    });
            })
            ->when($request->current_id, function ($q) use ($request) {
                $q->where('id', '!=', $request->current_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($suppliers->isEmpty()) {
            return response()->json(['exists' => false]);
        }

        $lawyerRejected = false;
        $lawyerNote = null;
        $lawyerFile = null;
        
        $auditorHasNote = false;
        $auditorNoteText = null;
        $auditorFile = null;

        $problematicCompanies = []; // Hanya untuk yang reject/note
        $allCompanyNames = [];      // Untuk menampung SEMUA perusahaan tempat NPWP ini ada

        foreach ($suppliers as $supplier) {
            $compName = $supplier->perusahaan->nama_perusahaan ?? 'Tanpa Nama Perusahaan';
            
            $allCompanyNames[] = $compName;

            $status = Suppliers_Status::on('tako-perusahaan')
                ->where('id_Supplier', $supplier->id)
                ->first();
            
            if (!$status) continue;

            if (!$lawyerRejected && strtolower($status->status_3 ?? '') === 'rejected') {
                $lawyerRejected = true;
                $lawyerNote = $status->status_3_keterangan;
                $lawyerFile = !empty($status->submit_3_path) ? route('file.view', ['path' => $status->submit_3_path]) : null;
                $problematicCompanies[] = $compName ;
            }

            if (!$auditorHasNote && !empty($status->status_4_keterangan)) {
                $auditorHasNote = true;
                $auditorNoteText = $status->status_4_keterangan;
                $auditorFile = !empty($status->status_4_path) ? route('file.view', ['path' => $status->status_4_path]) : null;
                
                $isAlreadyListed = false;
                foreach($problematicCompanies as $pc) {
                    if(str_contains($pc, $compName)) {
                        $isAlreadyListed = true; break;
                    }
                }
                
                if (!$isAlreadyListed) {
                    $problematicCompanies[] = $compName ;
                }
            }
        };
        
        $finalDisplayCompanies = [];

        if (!empty($problematicCompanies)) {
            $finalDisplayCompanies = $problematicCompanies;
        } else {
            $finalDisplayCompanies = array_values(array_unique($allCompanyNames));
        }

        return response()->json([
            'exists' => true,
            'nama_perusahaan' => $finalDisplayCompanies,

            // Data Lawyer
            'lawyer_rejected' => $lawyerRejected,
            'note' => $lawyerNote,
            'lawyer_file' => $lawyerFile,
            'lawyer_by' => $status->status_3_by ?? null,
            'lawyer_raw_path' => $status->submit_3_path ?? null,

            // Data Auditor
            'auditor_note' => $auditorHasNote,
            'auditor_note_text' => $auditorNoteText,
            'auditor_file' => $auditorFile,
            'auditor_by' => $status->status_4_by ?? null,
            'auditor_raw_path' => $status->status_4_path ?? null,
        ]);
    }

    private function sendSupplierToExternalApi(Supplier $supplier, User $user, ?string $uidMarketingOverride = null): void
    {
        $url = config('services.external_supplier.url');
        $token = config('services.external_supplier.token');

        if (!$url || !$token) {
            Log::warning('External supplier API belum dikonfigurasi.');
            return;
        }

        $perusahaan = Perusahaan::find($supplier->id_perusahaan);

        if (!$perusahaan) {
            Log::warning('Perusahaan tidak ditemukan untuk external supplier API.', [
                'supplier_id' => $supplier->id,
                'id_perusahaan' => $supplier->id_perusahaan,
            ]);

            return;
        }

        if (!$perusahaan->is_ppjk) {
            Log::info('Supplier tidak dikirim ke external API karena perusahaan bukan PPJK.', [
                'supplier_id' => $supplier->id,
                'id_perusahaan' => $supplier->id_perusahaan,
                'company_name' => $perusahaan->nama_perusahaan,
            ]);

            return;
        }

        $payload = [
            'uid_perusahaan' => $perusahaan->uid,
            'uid_marketing' => $uidMarketingOverride ?? $user->uid ?? '',
            'uid' => $supplier->uid,
            'nama_perusahaan' => $supplier->nama_perusahaan,
            'type' => 'external',
            'email' => $supplier->email,
            'nama' => $supplier->nama_personal,
            'no_npwp' => preg_replace('/\D/', '', $supplier->no_npwp ?? ''),
            'no_npwp_16' => preg_replace('/\D/', '', $supplier->no_npwp_16 ?? ''),
            'no_nib' => $supplier->nib ?? null,
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Gagal kirim supplier ke external API.', [
                    'supplier_id' => $supplier->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload,
                ]);

                return;
            }

            Log::info('Berhasil kirim supplier ke external API.', [
                'supplier_id' => $supplier->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (\Throwable $th) {
            Log::error('Error saat kirim supplier ke external API.', [
                'supplier_id' => $supplier->id,
                'error' => $th->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    private function serializeSupplierForFrontend(Supplier $supplier, bool $forEdit = false): array
    {
        $payload = $supplier->toArray();
        $payload['kategori_usaha'] = $forEdit
            ? ($supplier->kategori_usaha ?? '')
            : ($supplier->kategori_usaha ?? '-');
        $payload['no_telp'] = $forEdit
            ? $supplier->no_telp_list
            : ($supplier->formatted_no_telp ?: '-');
        $payload['no_telp_personal'] = $forEdit
            ? $supplier->no_telp_personal_list
            : ($supplier->formatted_no_telp_personal ?: '-');
        $payload['creator_role'] = $supplier->creator?->roles?->first()?->name ?? 'marketing';

        return $payload;
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function normalizeCsvHeader($value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
        $value = strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    private function mapImportedSupplierRow(array $row): array
    {
        $supplierCategory = $this->getCsvValue($row, ['supplier_category', 'category', 'jenis_supplier'], 'lain2');
        $cat = strtolower(trim($supplierCategory));
        if (!in_array($cat, ['trucking', 'pelayaran/freight', 'agent', 'lain2'])) {
            $cat = 'lain2';
        }
        $namaPerusahaan = $this->getCsvValue($row, ['nmmcust', 'namaperusahaan', 'namasupplier']);
        $alamatLengkap = $this->getCsvValue($row, ['alamat', 'alamatlengkap']);
        $alamatPenagihan = $this->getCsvValue($row, ['alamatfaktur', 'alamatpenagihan']);
        $kota = $this->getCsvValue($row, ['kotatextsupplier', 'nmmkota', 'kota', 'kotafaktur']);
        $emailPersonal = $this->sanitizeEmail($this->getCsvValue($row, ['email', 'emailpersonal', 'emailperusahaan']));
        $npwp = $this->sanitizeImportedNpwp($this->getCsvValue($row, ['npwp', 'nonpwp']));
        $npwp16 = $this->sanitizeImportedNpwp($this->getCsvValue($row, ['npwp16', 'nonpwp16', 'npwpbaru', 'nonpwpbaru']));
        
        $formattedNpwp15 = '';
        $formattedNpwp16 = '';

        if (strlen($npwp) === 15) {
            $formattedNpwp15 = $this->formatNpwpString($npwp);
        } else {
            $formattedNpwp15 = $npwp;
        }

        $digits16 = $npwp16 !== '' ? $npwp16 : $npwp;
        if (strlen($digits16) === 16) {
            $formattedNpwp16 = $this->formatNpwpString($digits16);
        } else {
            $formattedNpwp16 = '';
        }

        $nib = $this->sanitizeImportedNib($this->getCsvValue($row, ['nib', 'nonib']));
        $term = $this->getCsvValue($row, ['term', 'top']);
        $keterangan = $this->getCsvValue($row, ['keterangan', 'remarks', 'note']);
        $contactPerson = $this->getCsvValue($row, ['contactperson', 'namapersonal', 'namapj']);
        $hp1 = $this->getCsvValue($row, ['hp1', 'notelppersonal', 'notelppj']);
        $hp2 = $this->getCsvValue($row, ['hp2']);

        $jenisRaw = $this->getCsvValue($row, ['jenis_perusahaan', 'jenisperusahaan', 'jenis']);
        $jenisPerusahaan = 'Perusahaan Dalam Negeri';
        if (str_contains(strtolower($jenisRaw), 'luar negeri') || str_contains(strtolower($jenisRaw), 'luar_negeri')) {
            $jenisPerusahaan = 'Perusahaan Luar Negeri';
        }

        return $this->normalizeImportedSupplierTextFields([
            'supplier_category' => $cat,
            'kategori_usaha' => null,
            'nama_perusahaan' => $namaPerusahaan,
            'bentuk_badan_usaha' => $this->nullIfEmpty($this->inferBentukBadanUsaha($namaPerusahaan)),
            'jenis_perusahaan' => $jenisPerusahaan,
            'alamat_lengkap' => $this->nullIfEmpty($alamatLengkap),
            'kota' => $this->nullIfEmpty($kota),
            'no_telp' => $this->normalizeNullableArray([
                $this->getCsvValue($row, ['telp1', 'notelp']),
                $this->getCsvValue($row, ['telp2']),
            ]),
            'no_fax' => $this->nullIfEmpty($this->getCsvValue($row, ['fax', 'nofax'])),
            'alamat_penagihan' => $this->nullIfEmpty($alamatPenagihan),
            'email' => null,
            'website' => $this->nullIfEmpty($this->getCsvValue($row, ['website', 'web'])),
            'top' => $this->nullIfEmpty($this->extractTermsOfPayment($term, $keterangan)),
            'status_perpajakan' => ($formattedNpwp15 !== '' || $formattedNpwp16 !== '') ? 'pkp' : null,
            'no_npwp' => $this->nullIfEmpty($formattedNpwp15),
            'no_npwp_16' => $this->nullIfEmpty($formattedNpwp16),
            'nib' => $this->nullIfEmpty($nib),
            'nama_pj' => null,
            'no_ktp_pj' => null,
            'no_telp_pj' => null,
            'nama_personal' => $this->nullIfEmpty($contactPerson),
            'jabatan_personal' => null,
            'no_telp_personal' => $this->normalizeNullableArray([$hp1, $hp2]),
            'email_personal' => $this->nullIfEmpty($emailPersonal),
        ]);
    }

    private function getCsvValue(array $row, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeCsvHeader($key);

            if (array_key_exists($normalizedKey, $row)) {
                return $this->normalizeImportedValue($row[$normalizedKey]);
            }
        }

        return $default;
    }

    private function normalizeImportedValue($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        if ($value === '-' || strtolower($value) === 'null') {
            return '';
        }

        return $value;
    }

    private function sanitizeImportedNpwp(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = trim($value);
        $value = ltrim($value, "'` \t\n\r\0\x0B");

        if ($value === '') {
            return '';
        }

        return preg_replace('/\D/', '', $value);
    }

    private function sanitizeImportedNib(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = trim($value);
        $value = ltrim($value, "'` \t\n\r\0\x0B");

        if ($value === '') {
            return '';
        }

        return preg_replace('/\D/', '', $value);
    }

    private function sanitizeEmail(string $email): string
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }

    private function inferBentukBadanUsaha(string $namaPerusahaan): string
    {
        $value = strtoupper(trim($namaPerusahaan));

        return match (true) {
            str_starts_with($value, 'PT ') || str_starts_with($value, 'PT.') => 'Perseroan Terbatas',
            str_starts_with($value, 'CV ') || str_starts_with($value, 'CV.') => 'Commanditaire Vennootschap',
            str_starts_with($value, 'UD ') || str_starts_with($value, 'UD.') => 'Usaha Dagang',
            str_starts_with($value, 'PO ') || str_starts_with($value, 'PO.') => 'Perusahaan Perseorangan',
            default => '',
        };
    }

    private function extractTermsOfPayment(string $term, string $keterangan): string
    {
        if ($term !== '') {
            return $term;
        }

        if (preg_match('/top\s*[:\-]?\s*(.+)$/i', $keterangan, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function formatNpwp16(string $npwp): string
    {
        $digits = preg_replace('/\D/', '', $npwp);

        return strlen($digits) === 16 ? $digits : '';
    }

    private function findExistingSupplierForImport(int $idPerusahaan, ?string $namaPerusahaan, ?string $noNpwp, ?string $noNpwp16): ?Supplier
    {
        $namaPerusahaan = $namaPerusahaan ?? '';
        $noNpwp = $noNpwp ?? '';
        $noNpwp16 = $noNpwp16 ?? '';

        $query = Supplier::withTrashed()->where('id_perusahaan', $idPerusahaan);

        if ($noNpwp !== '' || $noNpwp16 !== '') {
            $query->where(function ($subQuery) use ($noNpwp, $noNpwp16) {
                if ($noNpwp !== '') {
                    $subQuery->orWhere('no_npwp', $noNpwp);
                }

                if ($noNpwp16 !== '') {
                    $subQuery->orWhere('no_npwp_16', $noNpwp16);
                }
            });

            return $query->first();
        }

        return $query->whereRaw('LOWER(nama_perusahaan) = ?', [strtolower($namaPerusahaan)])->first();
    }

    private function mergeImportedSupplierData(Supplier $supplier, array $payload): array
    {
        return $payload;
    }

    private function normalizeImportedSupplierTextFields(array $payload): array
    {
        $fieldsToNormalize = [
            'kategori_usaha',
            'nama_perusahaan',
            'bentuk_badan_usaha',
            'alamat_lengkap',
            'kota',
            'alamat_penagihan',
            'nama_pj',
            'nama_personal',
            'jabatan_personal',
        ];

        foreach ($fieldsToNormalize as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $payload[$field] = $this->normalizeImportedTitleCase($payload[$field]);
        }

        return $payload;
    }

    private function normalizeImportedTitleCase($value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function ensureSupplierStatusExists(int $supplierId, int $userId): void
    {
        $exists = DB::connection('tako-perusahaan')
            ->table('suppliers_statuses')
            ->where('id_Supplier', $supplierId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection('tako-perusahaan')->table('suppliers_statuses')->insert([
            'id_Supplier' => $supplierId,
            'id_user' => $userId,
            'submit_1_timestamps' => null,
            'status_1_by' => null,
            'status_1_timestamps' => null,
            'status_1_keterangan' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nullIfEmpty($value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function normalizeNullableArray(array $values): ?array
    {
        $normalized = [];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value)) {
                $parts = preg_split('/[,;\/]+/', $value);
                foreach ($parts as $part) {
                    $trimmed = trim($part);
                    if ($trimmed !== '') {
                        $normalized[] = $trimmed;
                    }
                }
            } else {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return empty($normalized) ? null : $normalized;
    }

    private function formatNpwpString(string $digits): string
    {
        $len = strlen($digits);
        if ($len === 15) {
            return substr($digits, 0, 2) . '.' .
                   substr($digits, 2, 3) . '.' .
                   substr($digits, 5, 3) . '-' .
                   substr($digits, 8, 1) . '.' .
                   substr($digits, 9, 3) . '.' .
                   substr($digits, 12, 3);
        }
        if ($len === 16) {
            return substr($digits, 0, 4) . ' ' .
                   substr($digits, 4, 4) . ' ' .
                   substr($digits, 8, 4) . ' ' .
                   substr($digits, 12, 4);
        }
        return $digits;
    }
}
