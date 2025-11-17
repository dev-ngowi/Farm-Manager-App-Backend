<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Muuzaji</title>
    <style>
        body { font-family: DejaVu Sans; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .total { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RISITI YA MUUZAJI</h1>
        <p><strong>Mkulima:</strong> {{ $farmer->user->firstname }} {{ $farmer->user->lastname }}</p>
        <p><strong>Tarehe:</strong> {{ $sale->sale_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Aina</th><td>{{ $sale->sale_type === 'Animal' ? 'Mnyama' : ($sale->sale_type === 'Milk' ? 'Maziwa' : 'Mengine') }}</td></tr>
        @if($sale->animal)
            <tr><th>Mnyama</th><td>{{ $sale->animal->tag_number }} - {{ $sale->animal->name }}</td></tr>
        @endif
        <tr><th>Mnunuzi</th><td>{{ $sale->buyer_name }}</td></tr>
        <tr><th>Kiasi</th><td>{{ $sale->quantity ? $sale->quantity . ' ' . $sale->unit : '1 unit' }}</td></tr>
        <tr><th>Bei ya Unit</th><td>TZS {{ number_format($sale->unit_price, 2) }}</td></tr>
        <tr><th>Jumla</th><td class="total">TZS {{ number_format($sale->total_amount, 2) }}</td></tr>
        <tr><th>Njia ya Malipo</th><td>{{ $sale->payment_method }}</td></tr>
    </table>

    <p><small>Risiti: {{ $sale->receipt_number ?? 'Hapana' }} | Maelezo: {{ $sale->notes ?? 'Hakuna' }}</small></p>
</body>
</html>
