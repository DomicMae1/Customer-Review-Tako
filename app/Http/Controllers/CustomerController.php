<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLink;
use App\Models\CustomerAttach;
use App\Models\Customers_Status;
use App\Models\Perusahaan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Clegginabox\PDFMerger\PDFMerger;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Process;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth('web')->user();

        if (!$user->hasPermissionTo('view-master-customer')) {
            throw UnauthorizedException::forPermissions(['view-master-customer']);
        }

        // --- Cek user tanpa perusahaan ---
        if ($user->hasRole(['user', 'manager', 'direktur']) && empty($user->id_perusahaan)) {
            return Inertia::render('m_customer/page', [
                'customers' => [],
                'company' => null,
                'flash' => ['success' => null, 'error' => 'Anda belum masuk di perusahaan manapun.'],
            ]);
        }

        // --- 1. Setup Query Dasar ---
        $query = Customer::with([
            'creator', 'perusahaan', 'status',
            'status.submit1By', 'status.status1Approver',
            'status.status2Approver', 'status.status3Approver',
            'customer_links'
        ]);

        // --- 2. Filter Scope Perusahaan ---
        if ($user->hasRole('user')) {
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
        }

        // =====================================================================
        // 3. LOGIC WORKFLOW (History Mode + Strict Hierarchy)
        // =====================================================================
        
        $statusTable = 'customers_statuses'; 

        // --- A. ROLE USER (MARKETING) ---
        // Lihat semua history buatan sendiri.
        if ($user->hasRole('user')) {
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
                ->pluck('id_Customer')
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

            // Step 2: Ambil ID Customer berdasarkan status
            
            // A. Data Verified (Sudah diapprove Manager)
            $verifiedByManagerIds = DB::connection('tako-perusahaan')
                ->table($statusTable)
                ->whereNotNull('status_1_timestamps')
                ->pluck('id_Customer')
                ->toArray();

            // B. Data Submitted (Baru disubmit User/Marketing)
            $submittedByUserIds = DB::connection('tako-perusahaan')
                ->table($statusTable)
                ->whereNotNull('submit_1_timestamps')
                ->pluck('id_Customer')
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
        $customerData = $suppliers->map(function ($customer) {
            $status = $customer->status;
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
                $tanggal = $customer->created_at;
                $label = 'diinput';
                $userName = $customer->creator?->name ?? '-';
            }

            // Fix Invalid Date: Pastikan tanggal dikirim sebagai string ISO
            $formattedDate = $tanggal ? \Carbon\Carbon::parse($tanggal)->toIso8601String() : null;

            return [
                'id' => $customer->id,
                'nama_perusahaan' => $customer->perusahaan?->nama_perusahaan ?? '-',
                'nama_customer' => $customer->nama_perusahaan ?? '-',
                'tanggal_status' => $tanggal,
                'status_label' => $label,
                'status' => $status?->status_3 ?? '-',
                
                // 6. Tanggal Status (Untuk memperbaiki "Invalid Date")
                // Frontend membaca ini untuk menampilkan "disubmit pada [TANGGAL]"
                'tanggal_status' => $formattedDate, 
                'created_at' => $customer->created_at, // Fallback

                // 7. Status Review (Approved/Rejected)                'status' => $status?->status_3 ?? '-',
                'nama_user' => $userName,
                'creator_name' => $customer->creator?->name ?? '-',
                'no_telp_personal' => $customer->no_telp_personal,
                'note' => $note,

                // Data Pelengkap Lainnya
                'user_id' => $customer->user_id,
                'creator' => [
                    'name' => $customer->creator?->name,
                    'role' => $customer->creator?->roles?->first()?->name,
                ],
                'customer_link' => [
                    'url' => $customer->customer_links?->url,
                ],
                
                // Data timestamp spesifik (untuk filter di frontend)
                'submit_1_timestamps' => $status?->submit_1_timestamps,
                'status_1_timestamps' => $status?->status_1_timestamps,
                'status_2_timestamps' => $status?->status_2_timestamps,
            ];
        });

        return Inertia::render('m_customer/page', [
            'customers' => $customerData,
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth('web')->user();

        if (!$user->hasPermissionTo('create-master-customer')) {
            throw UnauthorizedException::forPermissions(['create-master-customer']);
        }

        return Inertia::render('m_customer/table/add-data-form', [
            'flash' => [
                'success' => session('success'),
                'error' => session('error')
            ]
        ]);
    }

    /**
     * Share the form to customer
     */
    public function share()
    {
        $user = auth('web')->user();

        if (!$user->hasPermissionTo('create-master-customer')) {
            throw UnauthorizedException::forPermissions(['create-master-customer']);
        }

        return Inertia::render('m_customer/table/generate-data-form', [
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

        if (!$user->hasPermissionTo('create-master-customer')) {
            throw UnauthorizedException::forPermissions(['create-master-customer']);
        }

        DB::beginTransaction();

        $validated = $request->validate([
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
            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',
            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable|string',
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
            'tgl_customer' => 'nullable|date',

            'attachments' => 'required|array',
            'attachments.*.nama_file' => 'required|string',
            'attachments.*.path' => 'required|string',
            'attachments.*.type' => 'required|in:npwp,sppkp,ktp,nib',
        ]);


        try {
            $roles = $user->getRoleNames();

            if ($roles->contains('user')) {
                $idPerusahaan = $user->id_perusahaan;
            } elseif ($roles->contains('manager') || $roles->contains('direktur')) {
                $idPerusahaan = $request->id_perusahaan;
            }

            $customer = Customer::create(array_merge($validated, [
                'id_user' => $user->id,
                'id_perusahaan' => $idPerusahaan,
            ]));

            if (!empty($validated['attachments'])) {

                foreach ($validated['attachments'] as $attachment) {
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        CustomerAttach::create([
                            'customer_id' => $customer->id,
                            'nama_file'   => $attachment['nama_file'],
                            'path'        => $attachment['path'],
                            'type'        => $attachment['type'],
                        ]);
                    }
                }
            }

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

            DB::commit();

            $this->sendCustomerToExternalApi($customer, $user);

            return Inertia::location(route('customer.show', $customer->id));
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan: ' . $th->getMessage()]);
        }
    }

    public function storePublic(Request $request)
    {

        DB::beginTransaction();

        $validated = $request->validate([
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
            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',
            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable|string',
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
            'tgl_customer' => 'nullable|date',

            'attachments' => 'required|array',
            'attachments.*.nama_file' => 'required|string',
            'attachments.*.path' => 'required|string',
            'attachments.*.type' => 'required|in:npwp,sppkp,ktp,nib',
        ]);


        try {
            $userId = $request->input('user_id');

            $link = CustomerLink::on('tako-perusahaan')
                ->where('id_user', $userId)
                ->whereNull('id_customer')
                ->where('is_filled', false)
                ->latest('id_link')
                ->first();

            if (!$link) {
                throw new \Exception('Link tidak ditemukan atau sudah digunakan.');
            }

            $id_perusahaan = $link->id_perusahaan;

            $customer = Customer::create(array_merge($validated, [
                'id_user' => $userId,
                'id_perusahaan' => $id_perusahaan,
            ]));


            if (!empty($validated['attachments'])) {
                foreach ($validated['attachments'] as $attachment) {
                    // Ensure we only save string paths, skip blobs if any
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        CustomerAttach::create([
                            'customer_id' => $customer->id,
                            'nama_file'   => $attachment['nama_file'],
                            'path'        => $attachment['path'], // Expected: Final path string
                            'type'        => $attachment['type'],
                        ]);
                    }
                }
            }

            DB::connection('tako-perusahaan')->table('customers_statuses')->insert([
                'id_Customer' => $customer->id,
                'id_user' => $userId,
                'submit_1_timestamps' => null,
                'status_1_by' => null,
                'status_1_timestamps' => null,
                'status_1_keterangan' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            CustomerLink::on('tako-perusahaan')
                ->where('id_user', $userId)
                ->whereNull('id_customer')
                ->where('is_filled', false)
                ->latest('id_link')
                ->first()?->update([
                    'id_customer' => $customer->id,
                    'is_filled' => true,
                    'filled_at' => now(),
                ]);


            DB::commit();

            return response()->json([
                'message' => 'Data Anda berhasil dibuat!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan: ' . $th->getMessage()], 500);
        }
    }

    public function upload(Request $request)
    {
        // Validasi File
        $file = $request->file('pdf') ?? $request->file('file');
        if (!$file) {
            return response()->json(['error' => 'File tidak ditemukan'], 400);
        }

        // Ambil Parameter untuk Nama File
        $order       = str_pad((int)$request->input('order'), 3, '0', STR_PAD_LEFT);
        $npwp        = preg_replace('/[^0-9]/', '', $request->input('npwp_number'));
        $type        = strtolower($request->input('type'));

        // Simpan mode kompresi di nama file atau return ke frontend agar bisa dikirim balik saat store
        // Di sini kita hanya butuh nama file temp yang unik
        $ext         = $file->getClientOriginalExtension();
        $uniqueId = uniqid();
        $filename    = "{$npwp}-{$order}-{$type}-{$uniqueId}.{$ext}";

        $disk        = Storage::disk('customers_external');
        $tempDir     = 'temp';

        // Buat folder temp jika belum ada
        if (!$disk->exists($tempDir)) {
            $disk->makeDirectory($tempDir);
        }

        // Simpan File RAW langsung ke Temp (Tanpa Kompresi)
        $tempRel = "{$tempDir}/{$filename}";

        // Gunakan stream untuk efisiensi memori saat save
        $disk->put($tempRel, file_get_contents($file->getRealPath()));

        return response()->json([
            'status'    => 'success',
            'path'      => $tempRel,        // Path ini akan dikirim balik saat submit
            'nama_file' => $filename,
            'is_temp'   => true,
            'info'      => 'File uploaded to temp (uncompressed)'
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
            'customer_id' => 'nullable|integer', // TAMBAHAN: Butuh ID Customer untuk cek urutan file terakhir
        ]);

        $tempPath = $request->path;
        $originalName = $request->nama_file;
        $mode = $request->mode ?? 'medium';
        $idPerusahaan = $request->id_perusahaan;
        $role = strtolower($request->role ?? 'user');
        $customerId = $request->customer_id;
        $incrementOrder = (int)($request->increment_order ?? 1);

        $nextOrder = 1;

        // 1. Setup Disk & Slug
        $disk = Storage::disk('customers_external');

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

        if ($customerId) {
            $lastFromAttach = CustomerAttach::where('customer_id', $customerId)
                ->get()
                ->map(fn($r) => intval(explode('-', $r->nama_file)[1] ?? 0))
                ->max() ?? 0;

            // ... (logika cek status file sama) ...
            $status = \App\Models\Customers_Status::where('id_Customer', $customerId)->first();
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
        $newFileName = "{$npwp}-{$orderString}-{$docType}.{$ext}";

        $subFolder = ($role === 'user') ? 'attachment' : 'customers';
        if (in_array($docType, ['npwp', 'nib', 'sppkp', 'ktp'])) {
            $subFolder = 'attachment';
        }

        $targetDir = "{$companySlug}/{$subFolder}";
        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);
        }

        $finalRelPath = "{$targetDir}/{$newFileName}";

        $success = false;

        if ($ext === 'pdf') {
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

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $user = auth('web')->user();

        $hasGlobalAccess = $user->hasRole('auditor') || ($user->hasRole('lawyer') && empty($user->id_perusahaan));

        if (!$hasGlobalAccess && !$user->hasPermissionTo('view-master-customer')) {
            throw UnauthorizedException::forPermissions(['view-master-customer']);
        }

        if ($hasGlobalAccess) {
            $customer->load('attachments');

            return Inertia::render('m_customer/table/view-data-form', [
                'customer' => $customer,
                'attachments' => $customer->attachments,
            ]);
        }

        $userCompanyIds = $user->companies()->pluck('perusahaan.id')->toArray();

        if (!empty($user->id_perusahaan)) {
            $userCompanyIds[] = $user->id_perusahaan;
        }
        if (!in_array($customer->id_perusahaan, $userCompanyIds)) {
            abort(403, 'Anda tidak memiliki akses ke data customer ini.');
        }

        $customer->load('attachments');

        return Inertia::render('m_customer/table/view-data-form', [
            'customer' => $customer,
            'attachments' => $customer->attachments,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $user = auth('web')->user();

        $customer->load('attachments');

        $userCompanyIds = $user->companies()->pluck('perusahaan.id')->toArray();

        if (!empty($user->id_perusahaan)) {
            $userCompanyIds[] = $user->id_perusahaan;
        }
        if (!in_array($customer->id_perusahaan, $userCompanyIds)) {
            abort(403, 'Anda tidak memiliki akses ke data customer ini.');
        }

        return Inertia::render('m_customer/table/edit-data-form', [
            'customer' => $customer->load('attachments'),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $user = auth('web')->user();

        $createdDate = \Carbon\Carbon::parse($customer->created_at)->toDateString();
        $today = now()->toDateString();

        $canEditToday = $createdDate === $today;

        $validated = $request->validate([
            'id_perusahaan' => [
            'required',
                Rule::exists((new Perusahaan)->getTable(), 'id'),
            ],
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
            'nama_pj' => 'nullable|string',
            'no_ktp_pj' => 'nullable|string',
            'no_telp_pj' => 'nullable|string',
            'nama_personal' => 'nullable|string',
            'jabatan_personal' => 'nullable|string',
            'no_telp_personal' => 'nullable|string',
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
            'tgl_customer' => 'nullable|date',

            'attachments' => 'required|array',
            'attachments.*.nama_file' => 'required|string',
            'attachments.*.path' => 'required|string',
            'attachments.*.type' => 'required|in:npwp,sppkp,ktp,nib',
        ]);

        try {
            DB::beginTransaction();

            $customer->update($validated);
            $roles = $user->getRoleNames();

            if (isset($validated['attachments'])) {
                CustomerAttach::where('customer_id', $customer->id)->delete();

                foreach ($validated['attachments'] as $attachment) {
                    // Pastikan path bukan blob local (hanya defensive check)
                    if (!str_starts_with($attachment['path'], 'blob:')) {
                        CustomerAttach::create([
                            'customer_id' => $customer->id,
                            'nama_file'   => $attachment['nama_file'],
                            'path'        => $attachment['path'], // Path ini SUDAH FINAL dari proses frontend
                            'type'        => $attachment['type'],
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('customer.index')->with('success', 'Data Customer berhasil diperbarui!');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan: ' . $th->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {

        try {
            DB::beginTransaction();

            $customer->delete();

            DB::commit();

            return redirect()->route('customer.index')
                ->with('success', 'Data Customer berhasil dihapus (soft delete)!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('customer.index')
                ->with('error', 'Gagal menghapus Data Customer: ' . $e->getMessage());
        }
    }

    public function generatePdf($id)
    {
        Log::info("📄 Mulai generate PDF untuk customer ID: {$id}");

        $customer = Customer::with(['attachments', 'perusahaan'])->findOrFail($id);
        $user = auth('web')->user();

        $tempDir = storage_path("app/temp");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 2. Generate PDF Utama (Cover/Data Customer)
        $mainPdfPath = "{$tempDir}/customer_{$customer->id}_main.pdf";
        $mainPdf = Pdf::loadView('pdf.customer', [
            'customer' => $customer,
            'generated_by' => $user?->name ?? 'Guest',
        ])->setPaper('a4');
        file_put_contents($mainPdfPath, $mainPdf->output());

        // 3. Proses Attachment
        $attachmentPdfPaths = [];

        $externalRoot = '/mnt/Customer_Registration';

        if ($customer->attachments && count($customer->attachments) > 0) {
            foreach ($customer->attachments as $attachment) {

                // Filter: Hanya ambil dokumen penting (NPWP, NIB, KTP, dll)
                if (!in_array($attachment->type, ['npwp', 'nib', 'ktp'])) continue;

                // --- LOGIC PENGGABUNGAN PATH ---

                // 1. Ambil path dari DB: "pt-alpha/attachment/313...-003-ktp.pdf"
                $dbPath = $attachment->path;

                // 2. Bersihkan path (Jaga-jaga jika di DB tersimpan "storage/pt-alpha/...")
                // Kita hapus kata 'storage/' atau '/storage/' agar mendapatkan relative path yang murni
                $cleanRelativePath = ltrim(str_replace(['/storage/', 'storage/'], '', $dbPath), '/');

                // 3. Gabungkan Root Eksternal + Relative Path
                // Hasil: "/mnt/Customer_Registration/pt-alpha/attachment/313...-003-ktp.pdf"
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
        $mergedPath = "{$tempDir}/customer_{$customer->id}_full.pdf";
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

        $namaPerusahaan = preg_replace('/[^A-Za-z0-9_\- ]/', '', $customer->nama_perusahaan);
        $fileName = "Data Customer {$namaPerusahaan}.pdf";

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
        $link = CustomerLink::where('token', $token)->first();

        if (!$link) {
            abort(404, 'Link tidak valid atau sudah tidak tersedia.');
        }

        if ($link->is_filled) {
            return inertia('m_customer/table/filled-already');
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

        return inertia('m_customer/table/public-data-form', [
            'customer_name' => $link->nama_customer,
            'customer' => null,
            'token' => $token,
            'user_id' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
            'isFilled' => $link->is_filled,

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
        $link = CustomerLink::where('token', $token)->first();

        if (!$link) {
            abort(404, 'Token tidak ditemukan');
        }

        Log::info('Link detail testing', [
            'id_perusahaan' => $link->id_perusahaan,
        ]);

        $validated = $request->validate([
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

        $customer = Customer::create(array_merge($validated, [
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

        $customers = Customer::with('perusahaan')
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

        if ($customers->isEmpty()) {
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

        foreach ($customers as $customer) {
            $compName = $customer->perusahaan->nama_perusahaan ?? 'Tanpa Nama Perusahaan';
            
            $allCompanyNames[] = $compName;

            $status = Customers_Status::on('tako-perusahaan')
                ->where('id_Customer', $customer->id)
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
}
