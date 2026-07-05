<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class FileController extends Controller
{
    public function view($path)
    {
        // 1. Tentukan Disk yang mengarah ke /mnt/CR (via Docker Volume)
        $disk = Storage::disk('customers_external');

        // 2. Cek apakah file ada, jika tidak, coba di suppliers_external
        if (!$disk->exists($path)) {
            $supplierDisk = Storage::disk('suppliers_external');
            if ($supplierDisk->exists($path)) {
                $disk = $supplierDisk;
            } else {
                abort(404, 'File tidak ditemukan di penyimpanan server.');
            }
        }

        // 3. Ambil Full Path (Path Internal Container)
        $fullPath = $disk->path($path);

        // 4. Cek Mime Type
        $mimeType = $disk->mimeType($path) ?? 'application/octet-stream';

        // 5. Return File agar bisa dipreview di browser
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            // Header tambahan untuk keamanan
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}