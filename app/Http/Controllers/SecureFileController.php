<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SecureFileController extends Controller
{
    public function show($path)
    {
        // 1. Cek Folder Eksternal / Mount Point
        $externalBasePath = '/mnt/Customer_Registration';
        
        // Gabungkan base path dengan path yang diminta
        // Path yang dikirim dari route biasanya relatif, misal: "PT-A/file.pdf"
        $fullPath = "{$externalBasePath}/{$path}";

        // 2. Validasi Keberadaan File  
        if (!file_exists($fullPath)) {
            // Coba cek fallback ke storage lokal jika tidak ada di mount
            $localFallback = storage_path("app/public/{$path}");
            
            if (file_exists($localFallback)) {
                $fullPath = $localFallback;
            } else {
                abort(404, 'File not found or access denied.');
            }
        }

        // 3. Return File Response
        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath),
            'Content-Disposition' => 'inline', // Agar bisa tampil di browser/PDF
        ]);
    }
}
