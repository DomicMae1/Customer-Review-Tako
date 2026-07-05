<?php

namespace App\Mail;

use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;
    public $supplier;

    /**
     * Create a new message instance.
     */
    public function __construct(Supplier $supplier)
    {
        $this->supplier = $supplier;
    }

    /**
     * Get the message envelope.
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Notifikasi Pengajuan Supplier Baru ' . ($this->supplier->nama_perusahaan ?? ''),
        );
    }
    /**
     * Get the message content definition.
     */
    public function content()
    {
        return new Content(
            view: 'emails.supplier_submitted',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
