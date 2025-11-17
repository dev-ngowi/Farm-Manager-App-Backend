<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Mapato</title>
    <style>
        body { font-family: DejaVu Sans; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .total { font-weight: bold; font-size: 1.2em; }
        .bonus { color: #F59E0B; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RISITI YA MAPATO</h1>
        <p><strong>Mkulima:</strong> {{ $farmer->user->firstname }} {{ $farmer->user->lastname }}</p>
        <p><strong>Tarehe:</strong> {{ $income->income_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Aina ya Mapato</th><td>{{ $income->category->category_name }} @if($income->is_bonus)<span class="bonus"> (BONASI)</span>@endif</td></tr>
        @if($income->animal)
            <tr><th>Mnyama</th><td>{{ $income->animal->tag_number }} - {{ $income->animal->name }}</td></tr>
        @endif
        <tr><th>Kiasi</th><td>{{ $income->quantity ? $income->quantity . ' ' . $income->category->unit_of_measure : '1 unit' }}</td></tr>
        <tr><th>Bei ya Unit</th><td>TZS {{ number_format($income->unit_price ?? 0, 2) }}</td></tr>
        <tr><th>Jumla</th><td class="total">TZS {{ number_format($income->amount, 2) }}</td></tr>
        <tr><th>Njia ya Malipo</th><td>{{ $income->payment_method }}</td></tr>
        <tr><th>Mteja</th><td>{{ $income->buyer_customer ?? 'Hapana' }}</td></tr>
    </table>

    @if($income->is_bonus)
        <p><strong>Sababu ya Bonasi:</strong> {{ $income->bonus_reason }}</p>
    @endif

    <p><small>Imeandikishwa na: {{ $income->recordedBy->firstname }} | M-Pesa Code: {{ $income->mpesa_transaction_code ?? 'Hapana' }}</small></p>
</body>
</html>
