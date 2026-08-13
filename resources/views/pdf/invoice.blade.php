<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 24px; }
        .invoice-title { font-size: 24px; font-weight: bold; color: #4f46e5; }
        .number { font-size: 13px; color: #6b7280; }
        .meta { margin-bottom: 20px; font-size: 13px; }
        .meta strong { color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #eef2ff; text-align: right; padding: 10px; font-size: 13px; }
        td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .totals { margin-top: 20px; text-align: left; font-size: 14px; }
        .totals div { margin-bottom: 6px; }
        .grand-total { font-size: 18px; font-weight: bold; color: #4f46e5; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; color: #fff; background: #6b7280; }
        .status-paid { background: #16a34a; }
        .status-overdue { background: #dc2626; }
        .footer { margin-top: 40px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-title">Invoice</div>
        <div class="number">{{ $invoice->invoice_number }}</div>
        <span class="status status-{{ $invoice->status }}">{{ $invoice->status }}</span>
    </div>

    <div class="meta">
        <div><strong>Customer:</strong> {{ $invoice->user?->name }}</div>
        <div><strong>Email:</strong> {{ $invoice->user?->email }}</div>
        <div><strong>Issued:</strong> {{ $invoice->issued_at?->toDateString() }}</div>
        <div><strong>Due:</strong> {{ $invoice->due_at?->toDateString() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->invoiceItems as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>{{ number_format((float) $item->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>Subtotal: {{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</div>
        <div>Discount: {{ number_format((float) $invoice->discount, 2) }} {{ $invoice->currency }}</div>
        <div>Tax: {{ number_format((float) $invoice->tax, 2) }} {{ $invoice->currency }}</div>
        <div class="grand-total">Total: {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</div>
    </div>

    <div class="footer">
        {{ $invoice->notes ?? '' }}
    </div>
</body>
</html>
