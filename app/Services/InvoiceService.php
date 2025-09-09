<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;

class InvoiceService
{
    /**
     * Génère une facture pour un paiement donné
     */
    public function generateForPayment(Payment $payment)
    {
        // Si une facture existe déjà, on ne fait rien
        if ($payment->invoice) {
            return $payment->invoice;
        }

        // Création de la facture avec un status valide
        return Invoice::create([
            'user_id'            => $payment->user_id, // ✅ important
            'payment_id'         => $payment->id,
            'invoice_number'     => 'INV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
            'amount'             => $payment->amount,
            'currency'           => $payment->currency ?? 'TND',
            'status'             => 'pending', // ✅ valeur acceptée par ton ENUM
            'provider_payment_id'=> $payment->provider_payment_id ?? null,
        ]);
    }
}
