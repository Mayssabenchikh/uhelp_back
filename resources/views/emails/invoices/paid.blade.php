{{-- resources/views/emails/invoices/paid.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f8f9fa;
      color: #2c3e50;
      line-height: 1.6;
    }
    
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    
    .email-header {
      background: linear-gradient(135deg, #3498db, #2980b9);
      color: white;
      padding: 30px;
      text-align: center;
    }
    
    .success-icon {
      font-size: 48px;
      margin-bottom: 15px;
      display: block;
    }
    
    .email-header h1 {
      margin: 0;
      font-size: 24px;
      font-weight: 600;
    }
    
    .email-header p {
      margin: 10px 0 0 0;
      font-size: 16px;
      opacity: 0.9;
    }
    
    .email-content {
      padding: 30px;
    }
    
    .greeting {
      font-size: 16px;
      margin-bottom: 20px;
      color: #2c3e50;
    }
    
    .invoice-summary {
      background-color: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      padding: 20px;
      margin: 20px 0;
    }
    
    .invoice-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #e9ecef;
    }
    
    .invoice-row:last-child {
      border-bottom: none;
      font-weight: 600;
      font-size: 18px;
      color: #2c3e50;
      margin-top: 10px;
      padding-top: 15px;
      border-top: 2px solid #3498db;
    }
    
    .invoice-row span:first-child {
      color: #5a6c7d;
    }
    
    .invoice-row span:last-child {
      color: #2c3e50;
      font-weight: 500;
    }
    
    .status-badge {
      display: inline-block;
      background-color: #27ae60;
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      margin-left: 10px;
    }
    
    .payment-info {
      background: linear-gradient(135deg, #e8f5e8, #f0f9ff);
      border: 1px solid #27ae60;
      border-radius: 8px;
      padding: 20px;
      margin: 20px 0;
      text-align: center;
    }
    
    .payment-info h3 {
      color: #1e6b47;
      margin: 0 0 10px 0;
      font-size: 18px;
    }
    
    .payment-info p {
      color: #2d5a41;
      margin: 5px 0;
      font-size: 14px;
    }
    
    .cta-section {
      text-align: center;
      margin: 30px 0;
    }
    
    .btn {
      display: inline-block;
      padding: 12px 25px;
      background: linear-gradient(135deg, #3498db, #2980b9);
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      margin: 5px;
      transition: transform 0.2s;
    }
    
    .btn:hover {
      transform: translateY(-1px);
    }
    
    .btn-secondary {
      background: #6c757d;
    }
    
    .next-billing {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 8px;
      padding: 15px;
      margin: 20px 0;
      font-size: 14px;
    }
    
    .next-billing strong {
      color: #856404;
    }
    
    .footer {
      background-color: #f8f9fa;
      padding: 25px;
      text-align: center;
      border-top: 1px solid #e9ecef;
      font-size: 13px;
      color: #5a6c7d;
    }
    
    .footer p {
      margin: 5px 0;
    }
    
    .footer .company-name {
      color: #3498db;
      font-weight: 600;
    }
    
    .footer a {
      color: #3498db;
      text-decoration: none;
    }
    
    @media (max-width: 600px) {
      body {
        padding: 10px;
      }
      
      .email-content {
        padding: 20px;
      }
      
      .invoice-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      
      .btn {
        display: block;
        margin: 10px 0;
      }
    }
  </style>
</head>
<body>
  <div class="email-container">
    <!-- En-tête -->
    <div class="email-header">
      <span class="success-icon">✅</span>
      <h1>Paiement confirmé !</h1>
      <p>Votre facture a été réglée avec succès</p>
    </div>

    <!-- Contenu -->
    <div class="email-content">
      <div class="greeting">
        Bonjour {{ $invoice->client->name ?? 'Cher client' }},
      </div>
      
      <p>Nous vous confirmons que votre paiement a été traité avec succès.</p>

      <!-- Résumé de la facture -->
      <div class="invoice-summary">
        <div class="invoice-row">
          <span>Numéro de facture:</span>
          <span><strong>{{ $invoice->invoice_number }}</strong></span>
        </div>
        <div class="invoice-row">
          <span>Date d'émission:</span>
          <span>{{ $invoice->created_at->format('d/m/Y') }}</span>
        </div>
        <div class="invoice-row">
          <span>Date de paiement:</span>
          <span>{{ now()->format('d/m/Y') }}</span>
        </div>
        <div class="invoice-row">
          <span>Statut:PAYÉE</span>
        </div>
        <div class="invoice-row">
          <span>Montant total:</span>
          <span><strong>{{ number_format($invoice->amount, 2, ',', ' ') }} TND</strong></span>
        </div>
      </div>

      <!-- Confirmation de paiement -->
      <div class="payment-info">
        <h3>💳 Paiement confirmé</h3>
        <p><strong>Montant payé:</strong> {{ number_format($invoice->amount, 2, ',', ' ') }} TND</p>
        <p><strong>Date:</strong> {{ now()->format('d/m/Y à H:i') }}</p>
      </div>

      <!-- Prochaine facturation (si abonnement) -->
      @if($invoice->client->subscription ?? false)
      <div class="next-billing">
        <p><strong>📅 Prochaine facturation:</strong> dans 30 jours</p>
      </div>
      @endif

      <p>Vous trouverez la facture complète en pièce jointe de cet email.</p>

     

      <p style="margin-top: 25px; font-size: 14px;">
        Merci de votre confiance ! Pour toute question, notre équipe reste à votre disposition.
      </p>
    </div>

    <!-- Pied de page -->
    <div class="footer">
      <p>Cet email a été envoyé automatiquement par <span class="company-name">UHelp</span></p>
      <p style="margin-top: 15px; font-size: 12px;">
        © {{ date('Y') }} UHelp - Tous droits réservés
      </p>
    </div>
  </div>
</body>
</html>