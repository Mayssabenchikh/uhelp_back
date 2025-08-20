<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoicePaid extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function build()
    {
        $pdfPath = $this->invoice->meta['pdf_path'] ?? null;
        $mail = $this->subject('Votre facture ' . $this->invoice->invoice_number)
                     ->markdown('emails.invoices.paid')
                     ->with(['invoice' => $this->invoice]);

        if ($pdfPath && \Illuminate\Support\Facades\Storage::exists($pdfPath)) {
            $fullPath = Storage::path($pdfPath);
            $mail->attach($fullPath, [
                'as' => basename($fullPath),
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
