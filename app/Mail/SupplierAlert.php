<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SuppliersStatus;
use Illuminate\Support\Facades\Storage;

class SupplierAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $newCustomer;
    public $problemCustomer;
    public $status;

    public function __construct($user, $newCustomer, $problemCustomer, SuppliersStatus $status)
    {
        $this->user = $user;
        $this->newCustomer = $newCustomer;
        $this->problemCustomer = $problemCustomer;
        $this->status = $status;
    }

    public function build()
    {
        $email = $this->subject('⚠️ Alert: input data supplier dengan catatan bermasalah  (Bermasalah)')
            ->view('emails.supplier_duplicate_alert'); 

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

        return $email;
    }
}
