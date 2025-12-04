<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ substr($invoice->id, 0, 8) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #8552C5;
        }
        .company-info {
            flex: 1;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #8552C5;
            margin-bottom: 10px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-number {
            font-size: 18px;
            font-weight: bold;
            color: #8552C5;
            margin-bottom: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #8552C5;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        .client-info, .company-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead {
            background-color: #8552C5;
            color: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            font-weight: bold;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .total-row.final {
            border-top: 2px solid #8552C5;
            border-bottom: 2px solid #8552C5;
            font-weight: bold;
            font-size: 16px;
            padding: 12px 0;
            margin-top: 10px;
        }
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #8552C5;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 5px;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-sent {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-draft {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .payment-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $company->name ?? 'Workero' }}</div>
            @if($company->address ?? null)
                <div>{{ $company->address['street'] ?? '' }}</div>
                <div>{{ $company->address['city'] ?? '' }}, {{ $company->address['state'] ?? '' }} {{ $company->address['zip_code'] ?? '' }}</div>
            @endif
            @if($company->phone ?? null)
                <div>Phone: {{ $company->phone }}</div>
            @endif
            @if($company->email ?? null)
                <div>Email: {{ $company->email }}</div>
            @endif
        </div>
        <div class="invoice-info">
            <div class="invoice-number">INVOICE #{{ strtoupper(substr($invoice->id, 0, 8)) }}</div>
            <div>Date: {{ \Carbon\Carbon::parse($createdAt)->format('F d, Y') }}</div>
            <div>Due Date: {{ \Carbon\Carbon::parse($dueDate)->format('F d, Y') }}</div>
            <div class="status-badge status-{{ $invoice->status }}">
                {{ strtoupper($invoice->status) }}
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Bill To</div>
        <div class="client-info">
            <div><strong>{{ $client->name }}</strong></div>
            @if($client->email)
                <div>Email: {{ $client->email }}</div>
            @endif
            @if($client->phone)
                <div>Phone: {{ $client->phone }}</div>
            @endif
            @if($client->address)
                <div>{{ $client->address['street'] ?? '' }}</div>
                <div>{{ $client->address['city'] ?? '' }}, {{ $client->address['state'] ?? '' }} {{ $client->address['zip_code'] ?? '' }}</div>
            @endif
        </div>
    </div>

    @if($job)
    <div class="section">
        <div class="section-title">Job Information</div>
        <div>
            <div><strong>Job:</strong> {{ $job->title ?? 'N/A' }}</div>
            @if($job->description)
                <div><strong>Description:</strong> {{ $job->description }}</div>
            @endif
        </div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Items</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Tax Rate</th>
                    <th class="text-right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">£{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($item->tax_rate, 2) }}%</td>
                    <td class="text-right">£{{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>£{{ number_format($subtotal, 2) }}</span>
        </div>
        <div class="total-row">
            <span>Tax:</span>
            <span>£{{ number_format($taxAmount, 2) }}</span>
        </div>
        <div class="total-row final">
            <span>Total:</span>
            <span>£{{ number_format($total, 2) }}</span>
        </div>
    </div>

    @if($invoice->status === 'paid' && $paidDate)
    <div class="payment-info">
        <strong>Payment Information:</strong><br>
        Paid on: {{ \Carbon\Carbon::parse($paidDate)->format('F d, Y') }}<br>
        Payment Method: {{ strtoupper(str_replace('_', ' ', $invoice->payment_method ?? 'N/A')) }}
    </div>
    @endif

    @if($invoice->notes)
    <div class="notes">
        <strong>Notes:</strong><br>
        {{ $invoice->notes }}
    </div>
    @endif

    <div class="footer">
        <div>Thank you for your business!</div>
        <div>For questions about this invoice, please contact us at {{ $company->email ?? 'support@workero.com' }}</div>
        @if($invoice->status !== 'paid')
        <div style="margin-top: 10px; font-weight: bold;">Please pay by {{ \Carbon\Carbon::parse($dueDate)->format('F d, Y') }}</div>
        @endif
    </div>
</body>
</html>

