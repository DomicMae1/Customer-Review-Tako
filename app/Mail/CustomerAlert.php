<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Customers_Status;
use Illuminate\Support\Facades\Storage;
use App\Models\CustomerAttach;

class CustomerAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $newCustomer;
    public $problemCustomer;
    public $status;

    public function __construct($user, $newCustomer, $problemCustomer, Customers_Status $status)
    {
        $this->user = $user;
        $this->newCustomer = $newCustomer;
        $this->problemCustomer = $problemCustomer;
        $this->status = $status;
    }

    public function build()
    {
        $email = $this->subject('⚠️ Alert: input data customer dengan catatan bermasalah  (Bermasalah)')
            ->view('emails.duplicate_alert'); // Pastikan view ini ada

        // Attach File Lawyer
        if ($this->status->submit_3_path) {
            // Path absolut dari disk 'customers_external'
            $fullPath = Storage::disk('customers_external')->path(
                $this->status->submit_3_path
            );

            if (file_exists($fullPath)) {
                $namaFileLawyer = $this->status->submit_3_nama_file ?? basename($fullPath);

                $email->attach($fullPath, [
                    'as' => $namaFileLawyer,
                    'mime' => 'application/pdf',
                ]);
            }
        }

        if ($this->status->status_4_path) {
            // Path absolut dari disk 'customers_external'
            $fullPath = Storage::disk('customers_external')->path(
                $this->status->status_4_path
            );

            if (file_exists($fullPath)) {
                $namaFileLawyer = $this->status->status_4_nama_file ?? basename($fullPath);

                $email->attach($fullPath, [
                    'as' => $namaFileLawyer,
                    'mime' => 'application/pdf',
                ]);
            }
        }

        $customerId = $this->newCustomer->id ?? $this->newCustomer->id ?? null;

        if ($customerId) {
            $files = CustomerAttach::where('customer_id', $customerId)
                ->whereIn('type', ['npwp', 'nib', 'ktp', 'sppkp'])
                ->get();

            foreach ($files as $file) {

                $url = $file->path;

                // Ambil path mulai dari /file/view/...
                $parsed = parse_url($url, PHP_URL_PATH);

                // Buang prefix "/file/view/"
                $cleanPath = preg_replace('#^/file/view/#', '', $parsed);

                // Sekarang cleanPath = "ud-cherry/customers/xxx-npwp.pdf"
                // Ini valid untuk disk customers_external

                $realPath = Storage::disk('customers_external')->path($cleanPath);

                if (file_exists($realPath)) {
                    $email->attach($realPath, [
                        'as' => $file->nama_file, // contoh: NPWP.pdf
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        }

        return $email;
    }
}
