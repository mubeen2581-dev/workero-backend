<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote from {{ $quote->company->name ?? 'Workero' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .quote-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        .info-value {
            color: #212529;
            font-weight: 500;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-draft { background-color: #e9ecef; color: #495057; }
        .status-sent { background-color: #cfe2ff; color: #084298; }
        .status-accepted { background-color: #d1e7dd; color: #0f5132; }
        .status-rejected { background-color: #f8d7da; color: #842029; }
        .status-expired { background-color: #fff3cd; color: #664d03; }
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #212529;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .group-section {
            margin-bottom: 30px;
        }
        .group-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-left: 4px solid #667eea;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 16px;
            color: #495057;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .items-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .item-description {
            font-weight: 500;
            color: #212529;
        }
        .item-meta {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
        }
        .option-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        .option-good { background-color: #d1e7dd; color: #0f5132; }
        .option-better { background-color: #cfe2ff; color: #084298; }
        .option-best { background-color: #fff3cd; color: #664d03; }
        .option-optional { background-color: #f8d7da; color: #842029; }
        .text-right {
            text-align: right;
        }
        .text-bold {
            font-weight: 700;
        }
        .totals-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 16px;
        }
        .total-row.grand-total {
            border-top: 2px solid #667eea;
            margin-top: 15px;
            padding-top: 20px;
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        .notes-section {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        .notes-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #664d03;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 30px 0;
            text-align: center;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 20px 15px;
            }
            .items-table {
                font-size: 14px;
            }
            .items-table th,
            .items-table td {
                padding: 8px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Your Quote is Ready</h1>
            <p>{{ $quote->company->name ?? 'Workero' }}</p>
        </div>

        <div class="content">
            <div class="quote-info">
                <div class="info-row">
                    <span class="info-label">Quote #</span>
                    <span class="info-value">{{ substr($quote->id, 0, 8) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Client</span>
                    <span class="info-value">{{ $quote->client->name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Valid Until</span>
                    <span class="info-value">{{ $quote->valid_until->format('F d, Y') }}</span>
                </div>
            </div>

            @php
                $groupedItems = $quote->getGroupedItems();
            @endphp

            @if($groupedItems->count() > 0)
                @foreach($groupedItems as $groupName => $items)
                    <div class="group-section">
                        <div class="group-header">
                            {{ $groupName ?: 'Items' }}
                        </div>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>
                                            <div class="item-description">
                                                {{ $item->description }}
                                                @if($item->option_type)
                                                    <span class="option-badge option-{{ $item->option_type }}">
                                                        {{ ucfirst($item->option_type) }}
                                                    </span>
                                                @endif
                                                @if($item->is_optional)
                                                    <span class="option-badge option-optional">Optional</span>
                                                @endif
                                            </div>
                                            @if($item->category)
                                                <div class="item-meta">{{ $item->category }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                                        <td class="text-right">£{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-right text-bold">£{{ number_format($item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @else
                <div class="section-title">Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quote->items as $item)
                            <tr>
                                <td>
                                    <div class="item-description">{{ $item->description }}</div>
                                </td>
                                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                                <td class="text-right">£{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-right text-bold">£{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span class="text-bold">£{{ number_format($quote->subtotal, 2) }}</span>
                </div>
                <div class="total-row">
                    <span>Tax ({{ number_format($quote->items->avg('tax_rate') ?? 0, 2) }}%)</span>
                    <span class="text-bold">£{{ number_format($quote->tax_amount, 2) }}</span>
                </div>
                <div class="total-row grand-total">
                    <span>Total</span>
                    <span>£{{ number_format($quote->total, 2) }}</span>
                </div>
            </div>

            @if($quote->notes)
                <div class="notes-section">
                    <h3>Notes & Terms</h3>
                    <p>{{ $quote->notes }}</p>
                </div>
            @endif

            @if($quote->requires_esignature && $quote->esignature_status !== 'signed')
                <div style="text-align: center; margin: 40px 0;">
                    <a href="{{ config('app.frontend_url') }}/quotes/{{ $quote->id }}/sign" class="cta-button">
                        Review & Sign Quote
                    </a>
                </div>
            @else
                <div style="text-align: center; margin: 40px 0;">
                    <a href="{{ config('app.frontend_url') }}/quotes/{{ $quote->id }}" class="cta-button">
                        View Quote Online
                    </a>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>This quote is valid until {{ $quote->valid_until->format('F d, Y') }}</p>
            <p style="margin-top: 10px;">
                Questions? Contact us at 
                <a href="mailto:{{ $quote->company->email ?? 'support@workero.com' }}">
                    {{ $quote->company->email ?? 'support@workero.com' }}
                </a>
            </p>
            <p style="margin-top: 15px; font-size: 12px;">
                &copy; {{ date('Y') }} {{ $quote->company->name ?? 'Workero' }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
