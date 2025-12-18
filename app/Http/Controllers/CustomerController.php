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

        $customerStatus = Customers_Status::on('tako-perusahaan')->get();
        $query = Customer::with([
            'creator',
            'perusahaan',
            'status',
            'status.submit1By',
            'status.status1Approver',
            'status.status2Approver',
            'status.status3Approver',
            'customer_links'
        ]);

        if ($user->hasRole('user')) {
            if ($user->id_perusahaan) {
                $query->where('id_perusahaan', $user->id_perusahaan)
                    ->where('id_user', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole(['manager', 'direktur', 'lawyer'])) {
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

        $suppliers = $query->get();

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

            return [
                'id' => $customer->id,
                'nama_perusahaan' => $customer->perusahaan?->nama_perusahaan ?? '-',
                'nama_customer' => $customer->nama_perusahaan ?? '-',
                'tanggal_status' => $tanggal,
                'status_label' => $label,
                'status' => $status?->status_3 ?? '-',
                'note' => $note,
                'nama_user' => $userName,
                'creator_name' => $customer->creator?->name ?? '-',
                'no_telp_personal' => $customer->no_telp_personal,
                'creator' => [
                    'name' => $customer->creator?->name,
                    'role' => $customer->creator?->roles?->first()?->name,
                ],
                'submit_1_timestamps' => $status?->submit_1_timestamps,
                'status_2_timestamps' => $status?->status_2_timestamps,
                'customer_link' => [
                    'url' => $customer->customer_links?->url,
                ],
                'user_id' => $customer->user_id,
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

    // public function upload(Request $request)
    // {
    //     $file = $request->file('file');

    //     $filename = time() . '_' . $file->getClientOriginalName();

    //     $path = $file->storeAs('customers', $filename, 'public');

    //     $url = url(Storage::url($path)); 

    //     return response()->json([
    //         'path' => $url,           
    //         'nama_file' => $filename,
    //     ]);
    // }

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
        $filename    = "{$npwp}-{$order}-{$type}.{$ext}";

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

        // =========================================================
        // A. HITUNG NOMOR URUT (AUTO INCREMENT ORDER)
        // =========================================================
        
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
            
            // Urutan = Max di DB + Urutan batch ini
            $nextOrder = $maxDbOrder + $incrementOrder;

        } else {
            $nextOrder = $incrementOrder; 
        }
        $orderString = str_pad($nextOrder, 3, '0', STR_PAD_LEFT);

        // =========================================================
        // B. GENERATE NAMA FILE BARU
        // =========================================================
        
        $npwp = preg_replace('/[^0-9]/', '', $request->npwp_number) ?: '0000000000000000';
        
        $docType = $request->type ? strtolower($request->type) : 'document';
        
        // Mapping tipe dokumen
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
        // Gunakan $orderString hasil perhitungan di atas
        $newFileName = "{$npwp}-{$orderString}-{$docType}.{$ext}";

        // =========================================================
        // C. TENTUKAN FOLDER TUJUAN
        // =========================================================
        
        $subFolder = ($role === 'user') ? 'attachment' : 'customers';
        if (in_array($docType, ['npwp', 'nib', 'sppkp', 'ktp'])) {
            $subFolder = 'attachment';
        }

        $targetDir = "{$companySlug}/{$subFolder}";
        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);
        }
        
        $finalRelPath = "{$targetDir}/{$newFileName}";

        // =========================================================
        // D. PROSES KOMPRESI & PINDAH (LOGIC TETAP SAMA)
        // =========================================================

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

    // Helper Ghostscript (Private)
    private function runGhostscript($inputPath, $outputPath, $mode)
    {
        $settings = [
            'small'  => ['-dPDFSETTINGS=/ebook', '-dColorImageResolution=150', '-dGrayImageResolution=150', '-dMonoImageResolution=150'],
            'medium' => ['-dPDFSETTINGS=/ebook', '-dColorImageResolution=200', '-dGrayImageResolution=200', '-dMonoImageResolution=200'],
            'high'   => ['-dPDFSETTINGS=/printer', '-dColorImageResolution=300', '-dGrayImageResolution=300', '-dMonoImageResolution=300'],
        ];
        $config = $settings[$mode] ?? $settings['medium'];

        // Deteksi OS untuk Path Ghostscript
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $gsExe = $isWindows ? 'C:\Program Files\gs\gs10.05.1\bin\gswin64c.exe' : '/usr/bin/gs';

        // Pastikan input path kompatibel dengan OS (terutama Windows backslashes)
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
            '-o', $outputPath, 
            $inputPath
        ], $config);

        try {
            $process = new Process($cmd);
            $process->setTimeout(300); 
            $process->run();

            // Log Error Output jika gagal (Sangat membantu debugging)
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

        if (!$user->hasRole('auditor') && !$user->hasPermissionTo('view-master-customer')) {
            throw UnauthorizedException::forPermissions(['view-master-customer']);
        }

        if ($user->hasRole('auditor')) {
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

        return response()->download($finalPath, "Review_Customer_{$customer->id}.pdf", [
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

        Log::info('Link detail', [
            'id_user' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
            'token' => $token,
        ]);


        return inertia('m_customer/table/public-data-form', [
            'customer_name' => $link->nama_customer,
            'customer' => null,
            'token' => $token,
            'user_id' => $link->id_user,
            'id_perusahaan' => $link->id_perusahaan,
            'isFilled' => $link->is_filled,
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

        // 1. Cari customer berdasarkan NPWP 15 digit atau NPWP 16 digit
        $customer = Customer::where('no_npwp', $request->no_npwp)
            ->orWhere('no_npwp_16', $request->no_npwp_16)
            ->first();

        // Jika customer tidak ditemukan
        if (!$customer) {
            return response()->json([
                'exists' => false,
            ]);
        }

        // 2. Ambil id customer
        $customerId = $customer->id;

        // 3. Ambil data status dari tabel customer_statuses
        $status = Customers_Status::where('id_Customer', $customerId)->first();

        // Jika tidak ada record status
        if (!$status) {

            return response()->json([
                'exists' => true,
                'lawyer_rejected' => false,
                'note' => null,
                'auditor_note' => false,
                'auditor_note_text' => null,
            ]);

        }

        // 4. Cek lawyer reject (status_3)
        $isRejected = strtolower($status->status_3 ?? '') === 'rejected';

        // 5. Cek apakah auditor menambahkan catatan (status_4_keterangan)
        $auditorHasNote = !empty($status->status_4_keterangan);

        return response()->json([
            'exists' => true,
            // Lawyer reject
            'lawyer_rejected' => $isRejected,
            'note' => $isRejected ? ($status->status_3_keterangan ?? null) : null,
            // Auditor note
            'auditor_note' => $auditorHasNote,
            'auditor_note_text' => $auditorHasNote ? $status->status_4_keterangan : null,
        ]);
    }

}
