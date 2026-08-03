<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierAttach;
use App\Models\Perusahaan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ExternalSupplierReceiveController extends Controller
{
    /**
     * Receive supplier data from external application.
     * 
     * Endpoint: POST /api/supplier/receive
     * Auth: Sanctum (Bearer token)
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

        if (!$user->can('supplier.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Lacking supplier.create permission.',
            ], 403);
        }

        Log::info('External supplier API received headers:', $request->headers->all());
        Log::info('External supplier API received input:', $request->all());

        // Preprocess raw JSON content to strip invalid trailing commas
        $rawContent = $request->getContent();
        if (!empty($rawContent)) {
            $firstChar = substr(trim($rawContent), 0, 1);
            if ($firstChar === '{' || $firstChar === '[') {
                $cleanedContent = preg_replace('/,\s*([}\]])/', '$1', $rawContent);
                $decoded = json_decode($cleanedContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge($decoded);
                    Log::info('External supplier API parsed and merged cleaned JSON input:', $request->all());
                } else {
                    Log::warning('External supplier API failed to decode cleaned JSON: ' . json_last_error_msg());
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'uid' => 'required|string',
            'uid_marketing' => 'required|string',
            'uid_perusahaan' => 'required|string',
            'type' => 'nullable|string',
            'kategori' => 'nullable|string',
            'kategori_lain' => 'nullable|string',
            'ownership' => 'nullable|string',
            'created_by' => 'nullable|string',
            'updated_by' => 'nullable|string',
            'nama' => 'nullable|string',
            'email_to' => 'nullable|string',
            'email_cc' => 'nullable|string',

            'supplier_category' => 'nullable|string',
            'jenis_perusahaan' => 'nullable|string',
            'kategori_usaha' => 'required|string',
            'nama_perusahaan' => 'required|string',
            'bentuk_badan_usaha' => 'required|string',
            'alamat_lengkap' => 'required|string',
            'kota' => 'required|string',
            'no_telp' => 'nullable',
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
            'no_telp_personal' => 'nullable',
            'email_personal' => 'nullable|email',

            // Bank Accounts array input
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_holder' => 'required_with:bank_accounts|string',

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

        $marketingUser = User::where('uid', $validated['uid_marketing'])->first();
        if (!$marketingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => [
                    'uid_marketing' => [
                        'User marketing tidak ditemukan.',
                    ],
                ],
            ], 422);
        }

        $targetPerusahaan = Perusahaan::where('uid', $validated['uid_perusahaan'])->first();
        if (!$targetPerusahaan) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => [
                    'uid_perusahaan' => [
                        'Perusahaan tidak ditemukan.',
                    ],
                ],
            ], 422);
        }

        $supplierFields = [
            'supplier_category', 'kategori_usaha', 'nama_perusahaan', 'bentuk_badan_usaha', 'alamat_lengkap',
            'kota', 'no_telp', 'no_fax', 'alamat_penagihan', 'email', 'website', 'top',
            'status_perpajakan', 'no_npwp', 'no_npwp_16', 'nib', 'nama_pj', 'no_ktp_pj',
            'no_telp_pj', 'nama_personal', 'jabatan_personal', 'no_telp_personal', 'email_personal'
        ];
        if ($request->has('jenis_perusahaan')) {
            $supplierFields[] = 'jenis_perusahaan';
        }
        $supplierData = array_intersect_key($validated, array_flip($supplierFields));

        if (empty($supplierData['supplier_category'])) {
            $supplierData['supplier_category'] = $validated['kategori'] ?? 'Lokal';
        }

        if (isset($supplierData['no_npwp_16']) && $supplierData['no_npwp_16'] !== '') {
            $digits16 = preg_replace('/\D/', '', (string) $supplierData['no_npwp_16']);
            if (strlen($digits16) === 16) {
                $supplierData['no_npwp_16'] = substr($digits16, 0, 4) . ' ' .
                                              substr($digits16, 4, 4) . ' ' .
                                              substr($digits16, 8, 4) . ' ' .
                                              substr($digits16, 12, 4);
            } else {
                $supplierData['no_npwp_16'] = $digits16;
            }
        }

        if (isset($supplierData['no_npwp']) && $supplierData['no_npwp'] !== '') {
            $digits15 = preg_replace('/\D/', '', (string) $supplierData['no_npwp']);
            if (strlen($digits15) === 15) {
                $supplierData['no_npwp'] = substr($digits15, 0, 2) . '.' .
                                           substr($digits15, 2, 3) . '.' .
                                           substr($digits15, 5, 3) . '-' .
                                           substr($digits15, 8, 1) . '.' .
                                           substr($digits15, 9, 3) . '.' .
                                           substr($digits15, 12, 3);
            } else {
                $supplierData['no_npwp'] = $digits15;
            }
        }

        $filesToDeleteOnRollback = [];
        $uploadedFilesInfo = [];

        try {
            DB::connection('tako-customer')->beginTransaction();
            DB::connection('tako-perusahaan')->beginTransaction();

            $supplier = new Supplier(array_merge($supplierData, [
                'id_user' => $marketingUser->id,
                'id_perusahaan' => $targetPerusahaan->id,
            ]));
            $supplier->uid = $validated['uid'];
            $supplier->save();

            $attachmentsMap = [
                'npwp' => ['key' => 'pdf_npwp', 'order' => '001'],
                'nib'  => ['key' => 'pdf_nib',  'order' => '002'],
                'ktp'  => ['key' => 'pdf_ktp',  'order' => '003'],
                'sppkp'=> ['key' => 'pdf_sppkp', 'order' => '004'],
            ];

            $disk = Storage::disk('suppliers_external');
            $companySlug = $targetPerusahaan ? Str::slug($targetPerusahaan->nama_perusahaan) : 'general';
            $targetDir = "{$companySlug}/supplier_attachment";

            if (!$disk->exists($targetDir)) {
                $disk->makeDirectory($targetDir);
            }

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
                    $npwpClean = preg_replace('/[^0-9]/', '', $supplier->no_npwp) ?: '0000000000000000';
                    $newFileName = "{$npwpClean}-{$config['order']}-{$docType}.pdf";
                    $finalRelPath = "{$targetDir}/{$newFileName}";

                    $tempName = 'api_sup_in_' . uniqid() . '.pdf';
                    $localInputPath = Storage::disk('local')->path("gs_processing/{$tempName}");
                    $file->storeAs('gs_processing', $tempName, 'local');

                    $localOutputName = 'api_sup_out_' . uniqid() . '.pdf';
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
                        $disk->put($finalRelPath, file_get_contents($file->getRealPath()));
                    }

                    $filesToDeleteOnRollback[] = $finalRelPath;

                    $supplierAttach = SupplierAttach::create([
                        'supplier_id' => $supplier->id,
                        'nama_file' => $newFileName,
                        'path' => $finalRelPath,
                        'type' => $docType,
                    ]);

                    $uploadedFilesInfo[] = [
                        'type' => $supplierAttach->type,
                        'nama_file' => $supplierAttach->nama_file,
                        'path' => $supplierAttach->path,
                    ];
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

            DB::connection('tako-perusahaan')->commit();
            DB::connection('tako-customer')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil disimpan.',
                'data' => [
                    'id' => $supplier->id,
                    'uid' => $supplier->uid,
                    'id_user' => $supplier->id_user,
                    'id_perusahaan' => $supplier->id_perusahaan,

                    'supplier_category' => $supplier->supplier_category,
                    'kategori_usaha' => $supplier->kategori_usaha ? ucfirst(strtolower($supplier->kategori_usaha)) : null,
                    'nama_perusahaan' => $supplier->nama_perusahaan,
                    'bentuk_badan_usaha' => $supplier->bentuk_badan_usaha,
                    'jenis_perusahaan' => $supplier->jenis_perusahaan,
                    'alamat_lengkap' => $supplier->alamat_lengkap,
                    'kota' => $supplier->kota,
                    'no_telp' => $supplier->no_telp,
                    'no_fax' => $supplier->no_fax,
                    'alamat_penagihan' => $supplier->alamat_penagihan,
                    'email' => $supplier->email,
                    'website' => $supplier->website,
                    'top' => $supplier->top,
                    'status_perpajakan' => $supplier->status_perpajakan,
                    'no_npwp' => $supplier->no_npwp,
                    'no_npwp_16' => $supplier->no_npwp_16,
                    'nib' => $supplier->nib,

                    'nama_pj' => $supplier->nama_pj,
                    'no_ktp_pj' => $supplier->no_ktp_pj,
                    'no_telp_pj' => $supplier->no_telp_pj,

                    'nama_personal' => $supplier->nama_personal,
                    'jabatan_personal' => $supplier->jabatan_personal,
                    'no_telp_personal' => $supplier->no_telp_personal,
                    'email_personal' => $supplier->email_personal,

                    'bank_accounts' => $validated['bank_accounts'] ?? [],
                    'attachments' => $uploadedFilesInfo,
                    'created_at' => $supplier->created_at,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::connection('tako-perusahaan')->rollBack();
            DB::connection('tako-customer')->rollBack();

            $disk = Storage::disk('suppliers_external');
            foreach ($filesToDeleteOnRollback as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan supplier.',
                'error' => $th->getMessage(),
            ], 500);
        }
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
