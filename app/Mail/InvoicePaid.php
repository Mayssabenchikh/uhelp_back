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
        // Sujet du mail
        $mail = $this->subject('Votre facture ' . $this->invoice->invoice_number)
                     // Utilise ta vue HTML personnalisée (PAS markdown)
                     ->view('emails.invoices.paid')
                     ->with([
                         'invoice' => $this->invoice,
                     ]);

        // Attacher le PDF si présent (méthode robuste pour local ou cloud)
        $pdfPath = $this->invoice->meta['pdf_path'] ?? null;

        if ($pdfPath && Storage::exists($pdfPath)) {
            try {
                // Essaye d'obtenir un chemin local (fonctionne pour disque local)
                $fullPath = Storage::path($pdfPath);
                $mail->attach($fullPath, [
                    'as' => basename($fullPath),
                    'mime' => 'application/pdf',
                ]);
            } catch (\Throwable $e) {
                // Si Storage::path() n'est pas disponible (ex: S3), attache les données en mémoire
                $pdfData = Storage::get($pdfPath);
                $mail->attachData($pdfData, basename($pdfPath), [
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }
}
