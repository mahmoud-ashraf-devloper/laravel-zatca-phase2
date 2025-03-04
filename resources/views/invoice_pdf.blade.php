<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>TAX INVOICE #{{ $data['invoice_number'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .header h1 {
            color: #5cb85c;
            margin-bottom: 5px;
        }
        .qr-code {
            text-align: center;
            margin-bottom: 20px;
        }
        .qr-code img {
            max-width: 150px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            color: #666;
        }
        .row {
            display: flex;
            margin-bottom: 15px;
        }
        .col {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f8f8f8;
            text-align: left;
        }
        .total {
            text-align: right;
            margin-top: 20px;
        }
        .total table {
            width: 300px;
            margin-left: auto;
        }
        .total table td {
            padding: 5px;
        }
        .footer {
            margin-top: 50px;
            font-size: 10px;
            text-align: center;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>TAX INVOICE</h1>
    <p>Invoice #: {{ $data['invoice_number'] }}</p>
    <p>Date: {{ $data['issue_date'] }}</p>
</div>

<div class="qr-code">
    <img src="{{ $qrCode }}" alt="ZATCA QR Code">
</div>

<div class="row">
    <div class="col">
        <div class="section">
            <h3>Seller</h3>
            <p><strong>{{ $data['seller_name'] }}</strong></p>
            <p>VAT #: {{ $data['seller_tax_number'] }}</p>
            @if(!empty($data['seller_address']))
                <p>{{ $data['seller_address'] }}</p>
            @endif
        </div>
    </div>

    <div class="col">
        <div class="section">
            <h3>Buyer</h3>
            <p><strong>{{ $data['buyer_name'] }}</strong></p>
            @if(!empty($data['buyer_tax_number']))
                <p>VAT #: {{ $data['buyer_tax_number'] }}</p>
            @endif
            @if(!empty($data['buyer_address']))
                <p>{{ $data['buyer_address'] }}</p>
            @endif
        </div>
    </div>
</div>

<div class="section">
    <h3>Items</h3>
    <table>
        <thead>
        <tr>
            <th width="40%">Description</th>
            <th width="10%">Quantity</th>
            <th width="15%">Unit Price</th>
            <th width="10%">VAT %</th>
            <th width="10%">VAT Amount</th>
            <th width="15%">Total</th>
        </tr>
        </thead>
        <tbody>
        @if(isset($document->items) && count($document->items) > 0)
            @foreach($document->items as $item)
                <tr>
                    <td>{{ $item->name ?? 'Item' }}</td>
                    <td>{{ $item->quantity ?? '1' }}</td>
                    <td>{{ number_format(($item->unit_price ?? 0), 2) }} SAR</td>
                    <td>{{ ($item->tax_rate ?? 15) }}%</td>
                    <td>{{ number_format(($item->tax_amount ?? 0), 2) }} SAR</td>
                    <td>{{ number_format(($item->total_amount ?? 0), 2) }} SAR</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="6" style="text-align: center;">No items available</td>
            </tr>
        @endif
        </tbody>
    </table>
</div>

<div class="total">
    <table>
        <tr>
            <td><strong>Subtotal:</strong></td>
            <td>{{ number_format($data['total_excluding_vat'], 2) }} SAR</td>
        </tr>
        <tr>
            <td><strong>VAT ({{ $data['tax_rate'] ?? 15 }}%):</strong></td>
            <td>{{ number_format($data['total_vat'], 2) }} SAR</td>
        </tr>
        <tr style="font-weight: bold; font-size: 14px;">
            <td>Total:</td>
            <td>{{ number_format($data['total_including_vat'], 2) }} SAR</td>
        </tr>
    </table>
</div>

<div class="footer">
    <p>This is a system-generated invoice and is valid without a signature.</p>
    <p>ZATCA Status: {{ $document->zatca_status ?? 'Not Submitted' }}</p>
    <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
</div>
</body>
</html>