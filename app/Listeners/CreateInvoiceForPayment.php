<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoicePaid;

class CreateInvoiceForPayment
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompleted $event): void
    {
        $payment = $event->payment->fresh();
        if (!$payment) return;

        // idempotence : si une facture existe déjà pour ce paiement, abandonner
        if ($payment->invoice()->exists()) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $user = $payment->user;
            $subscription = $payment->subscription;
            $plan = $subscription?->plan;

            // Générer un numéro de facture unique
            $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));

            // convertir amount stocké en "minor units" vers montant décimal lisible
            // (ton PaymentController utilise 1000 pour TND, 100 pour autres)
            $minor = $payment->amount ?? 0;
            $amount = ($payment->currency === 'TND') ? ($minor / 1000) : ($minor / 100);

            // création de l'invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'user_id' => $user->id,
                'admin_id' => 1, 
                'amount' => $amount,
                'status' => 'paid',
                'due_date' => now()->addDays(15),
                'meta' => ['source'=>'auto','payment_id'=>$payment->id],
                'paid_at' => now(),
                'payment_id' => $payment->id,
                'provider_payment_id' => $payment->provider_payment_id ?? null,
            ]);

            // Créer des lignes: si plan présent, ajouter une ligne d'abonnement
            if ($plan) {
                $lineDesc = 'Abonnement: ' . ($plan->name ?? 'Plan');
                $unitPrice = (float) $plan->price; // plan->price est decimal:2
                $qty = 1;
                $lineTotal = $unitPrice * $qty;

                $invoice->items()->create([
                    'description' => $lineDesc,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ]);
            } else {
                // fallback: si pas de plan, créer ligne générique à partir de payment->description
                $invoice->items()->create([
                    'description' => $payment->description ?? 'Paiement entré manuellement',
                    'qty' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ]);
            }

            // Générer le PDF et sauvegarder dans storage/app/invoices/
            $invoice = $invoice->fresh('items','client','admin');
            $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
            $filename = "invoices/invoice-{$invoice->invoice_number}.pdf";
            Storage::put($filename, $pdf->output());

            // enregistrer chemin dans meta
            $meta = $invoice->meta ?? [];
            $meta['pdf_path'] = $filename;
            $invoice->meta = $meta;
            $invoice->save();

            // envoyer email (queue si configurée)
            if ($user && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($user->email)->queue(new InvoicePaid($invoice));
                } catch (\Throwable $e) {
                    // ne pas bloquer la transaction si l'envoi échoue ; logguer
                    \Log::error('Invoice mail failed', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id]);
                }
            }
        });
    }
}
