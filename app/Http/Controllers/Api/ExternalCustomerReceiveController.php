<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAttach;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

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

            // Attachment inputs (PDF only, max 5MB)
            'pdf_npwp' => 'nullable|file|mimes:pdf|max:5120',
            'pdf_nib' => 'nullable|file|mimes:pdf|max:5120',
            'pdf_ktp' => 'nullable|file|mimes:pdf|max:5120',
            'pdf_sppkp' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Separate customer text data from uploaded files
        $customerFields = [
            'kategori_usaha', 'nama_perusahaan', 'bentuk_badan_usaha', 'alamat_lengkap',
            'kota', 'no_telp', 'no_fax', 'alamat_penagihan', 'email', 'website', 'top',
            'status_perpajakan', 'no_npwp', 'no_npwp_16', 'nib', 'nama_pj', 'no_ktp_pj',
            'no_telp_pj', 'nama_personal', 'jabatan_personal', 'no_telp_personal', 'email_personal'
        ];
        $customerData = array_intersect_key($validated, array_flip($customerFields));

        $filesToDeleteOnRollback = [];
        $uploadedFilesInfo = [];

        try {
            DB::connection('tako-customer')->beginTransaction();
            DB::connection('tako-perusahaan')->beginTransaction();

            $customer = Customer::create(array_merge($customerData, [
                'uid' => $this->generateCustomerUid(),
                'id_user' => $user->id,
                'id_perusahaan' => $idPerusahaan,
            ]));

            // Mapping dari request keys ke type attachment & order string
            $attachmentsMap = [
                'npwp' => [
                    'key' => 'pdf_npwp',
                    'order' => '001',
                ],
                'nib' => [
                    'key' => 'pdf_nib',
                    'order' => '002',
                ],
                'ktp' => [
                    'key' => 'pdf_ktp',
                    'order' => '003',
                ],
                'sppkp' => [
                    'key' => 'pdf_sppkp',
                    'order' => '004',
                ],
            ];

            // Setup Storage disk & Company slug
            $disk = Storage::disk('customers_external');
            $companySlug = 'general';
            if ($idPerusahaan) {
                $perusahaan = Perusahaan::find($idPerusahaan);
                if ($perusahaan) {
                    $companySlug = Str::slug($perusahaan->nama_perusahaan);
                }
            }

            $targetDir = "{$companySlug}/attachment";

            // Pastikan direktori target ada di disk customers_external
            if (!$disk->exists($targetDir)) {
                $disk->makeDirectory($targetDir);
            }

            // Pastikan local temporary directory untuk Ghostscript ada
            if (!Storage::disk('local')->exists('gs_processing')) {
                Storage::disk('local')->makeDirectory('gs_processing');
            }

            foreach ($attachmentsMap as $docType => $config) {
                $file = null;
                $key = $config['key'];
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                }

                if ($file) {
                    $npwpClean = preg_replace('/[^0-9]/', '', $customer->no_npwp) ?: '0000000000000000';
                    $newFileName = "{$npwpClean}-{$config['order']}-{$docType}.pdf";
                    $finalRelPath = "{$targetDir}/{$newFileName}";

                    // Proses Kompresi Ghostscript
                    $tempName = 'api_in_' . uniqid() . '.pdf';
                    $localInputPath = Storage::disk('local')->path("gs_processing/{$tempName}");
                    $file->storeAs('gs_processing', $tempName, 'local');

                    $localOutputName = 'api_out_' . uniqid() . '.pdf';
                    $localOutputPath = Storage::disk('local')->path("gs_processing/{$localOutputName}");

                    $compressResult = $this->runGhostscript($localInputPath, $localOutputPath, 'medium');

                    $successUpload = false;
                    if ($compressResult && file_exists($localOutputPath)) {
                        $disk->put($finalRelPath, file_get_contents($localOutputPath));
                        $successUpload = true;
                        @unlink($localOutputPath);
                    }
                    @unlink($localInputPath);

                    if (!$successUpload) {
                        // Fallback jika kompresi gagal: Simpan file asli
                        $disk->put($finalRelPath, file_get_contents($file->getRealPath()));
                    }

                    // Tambahkan ke list rollback
                    $filesToDeleteOnRollback[] = $finalRelPath;

                    // Simpan data attachment ke database
                    $customerAttach = CustomerAttach::create([
                        'customer_id' => $customer->id,
                        'nama_file' => $newFileName,
                        'path' => $finalRelPath,
                        'type' => $docType,
                    ]);

                    $uploadedFilesInfo[] = [
                        'type' => $customerAttach->type,
                        'nama_file' => $customerAttach->nama_file,
                        'path' => $customerAttach->path,
                    ];
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

                    'attachments' => $uploadedFilesInfo,
                    'created_at' => $customer->created_at,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::connection('tako-perusahaan')->rollBack();
            DB::connection('tako-customer')->rollBack();

            // Hapus file yang sempat ter-upload pada storage disk
            $disk = Storage::disk('customers_external');
            foreach ($filesToDeleteOnRollback as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

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
}
