<?php

namespace App\Mail;

use App\Models\Supplier;
use App\Models\SupplierAttach;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SuppliersStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class SupplierStatusRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $status;
    public $sender;
    public $nama;

    /**
     * Create a new message instance.
     */
    public function __construct(SuppliersStatus $status, User $sender, Supplier $nama)
    {
        $this->nama = $nama;
        $this->status = $status;
        $this->sender = $sender;
    }

    public function build()
    {
        $email = $this->subject('Status Supplier Ditolak oleh ' . $this->sender->name)
            ->view('emails.supplier_status_rejected');

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

        $supplierId = $this->status->id_Supplier;

        if ($supplierId) {
            $files = SupplierAttach::where('supplier_id', $supplierId)->get();

            foreach ($files as $file) {
                if ($file->path) {
                    $fullPath = Storage::disk('public')->path($file->path);

                    if (file_exists($fullPath)) {
                        $email->attach($fullPath, [
                            'as' => $file->nama_file,
                            'mime' => 'application/octet-stream',
                        ]);
                    }
                }
            }
        }

        return $email;
    }
}
