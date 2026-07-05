<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSupplierAuditorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $customer; // Supplier instance mapped to customer variable for view compatibility
    public $status;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct($supplier, $status, $user)
    {
        $this->customer = $supplier;
        $this->status = $status;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Supplier Baru Perlu Pengecekan Auditor',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-supplier-auditor',
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
