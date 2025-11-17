<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Gharama</title>
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
        <h1>RISITI YA GHARAMA</h1>
        <p><strong>Mkulima:</strong> {{ $farmer->user->firstname }} {{ $farmer->user->lastname }}</p>
        <p><strong>Tarehe:</strong> {{ $expense->expense_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Aina ya Gharama</th><td>{{ $expense->category->category_name }}</td></tr>
        <tr><th>Mnyama (ikiwa)</th><td>{{ $expense->animal?->tag_number ?? 'Hapana' }}</td></tr>
        <tr><th>Kiasi</th><td class="total">TZS {{ number_format($expense->amount, 2) }}</td></tr>
        <tr><th>Njia ya Malipo</th><td>{{ $expense->payment_method }}</td></tr>
        <tr><th>Muuzaji</th><td>{{ $expense->vendor_supplier ?? 'Hapana' }}</td></tr>
        <tr><th>Maelezo</th><td>{{ $expense->description ?? 'Hakuna' }}</td></tr>
    </table>

    <p><small>Imeandikishwa na: {{ $expense->recordedBy->firstname }} | Risiti: {{ $expense->receipt_number ?? 'Hapana' }}</small></p>
</body>
</html>
