@component('mail::message')
# Facture {{ $invoice->invoice_number }}

Bonjour {{ $invoice->client->name ?? $invoice->client->email }},

Merci pour votre paiement. Vous trouverez en pièce jointe la facture correspondant à la transaction.

- Montant : **{{ number_format($invoice->amount, 2, ',', ' ') }}**
- Date : {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d') : now()->format('Y-m-d') }}

Merci,<br>
{{ config('app.name') }}
@endcomponent
