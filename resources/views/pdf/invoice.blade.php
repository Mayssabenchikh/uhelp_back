<!doctype html>
<html>
<head>
    <meta charset="utf-8"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.6;
            background: #ffffff;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3498db;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            font-size: 14px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .invoice-info {
            text-align: right;
            background: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            min-width: 250px;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .invoice-meta {
            font-size: 13px;
        }
        
        .invoice-meta div {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-meta strong {
            color: #2c3e50;
        }
        
        .billing-section {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
        }
        
        .bill-to {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .bill-to-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .client-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .client-email {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .items-table thead th {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 14px;
        }
        
        .items-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .right {
            text-align: right;
        }
        
        .center {
            text-align: center;
        }
        
        .totals-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            min-width: 300px;
            border-collapse: collapse;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .totals-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .totals-table tr:last-child td {
            border-bottom: none;
            background: #3498db;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        .subtotal-label {
            font-weight: 500;
            color: #5a6c7d;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .admin-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-size: 13px;
            color: #5a6c7d;
        }
        
        .notes-section {
            flex: 1;
            margin-left: 30px;
        }
        
        .notes-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .notes-content {
            font-size: 13px;
            color: #5a6c7d;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #3498db;
        }
        
        .empty-row {
            text-align: center;
            color: #95a5a6;
            font-style: italic;
            padding: 30px;
        }
        
        .amount {
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        @media print {
            .invoice-container {
                padding: 20px;
            }
            
            .items-table {
                box-shadow: none;
            }
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #e74c3c;
            color: white;
        }
        
        .currency {
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-name">UHelp</div>
            </div>
            
            <div class="invoice-info">
                <div class="invoice-title">FACTURE</div>
                <div class="invoice-meta">
                    <div>
                        <span>Numéro:</span>
                        <strong>{{ $invoice->invoice_number }}</strong>
                    </div>
                    <div>
                        <span>Date d'émission:</span>
                        <strong>{{ $invoice->created_at->format('d/m/Y') }}</strong>
                    </div>
                    <div>
                        <span>Date d'échéance:</span>
                        <strong>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '—' }}</strong>
                        @if($invoice->due_date && $invoice->due_date < now())
                            <span class="status-badge">En retard</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Billing Information -->
        <div class="billing-section">
            <div class="bill-to">
                <div class="bill-to-title">Facturé à</div>
                <div class="client-name">
                    {{ $invoice->client->name ?? $invoice->client->email }}
                </div>
                @if($invoice->client->email && $invoice->client->name)
                <div class="client-email">
                    {{ $invoice->client->email }}
                </div>
                @endif
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width:100px" class="center">Quantité</th>
                    <th style="width:130px" class="right">Prix unitaire</th>
                    <th style="width:130px" class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->description }}</strong>
                    </td>
                    <td class="center">{{ $item->qty }}</td>
                    <td class="right amount">
                        <span class="currency">TND</span> {{ number_format($item->unit_price, 2, ',', ' ') }}
                    </td>
                    <td class="right amount">
                        <span class="currency">TND</span> {{ number_format($item->total, 2, ',', ' ') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="empty-row">
                        Aucune ligne de facture disponible
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="subtotal-label">Sous-total HT</td>
                    <td class="right amount">
                        <span class="currency">TND</span> {{ number_format($invoice->amount, 2, ',', ' ') }}
                    </td>
                </tr>
                {{-- Ajoute ici TVA / réductions si nécessaire --}}
                {{-- 
                <tr>
                    <td class="subtotal-label">TVA (20%)</td>
                    <td class="right amount">€ 0,00</td>
                </tr>
                --}}
                <tr>
                    <td><strong>TOTAL TTC</strong></td>
                    <td class="right">
                        <span class="currency">TND</span> {{ number_format($invoice->amount, 2, ',', ' ') }}
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="admin-info">
                <div><strong>Gestionnaire:</strong></div>
                <div>{{ $invoice->admin->name ?? 'Non assigné' }}</div>
            </div>
            
            @if(!empty($invoice->meta['notes']))
            <div class="notes-section">
                <div class="notes-title">Notes & Conditions</div>
                <div class="notes-content">
                    {{ $invoice->meta['notes'] }}
                </div>
            </div>
            @endif
        </div>
    </div>
</body>
</html>