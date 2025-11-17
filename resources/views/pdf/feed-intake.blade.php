<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Malisho</title>
    <style>
        body { font-family: DejaVu Sans; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA MALISHO</h1>
        <p>Ng'ombe: <strong>{{ $intake->animal->tag_number }} - {{ $intake->animal->name }}</strong></p>
        <p>Tarehe: {{ \Carbon\Carbon::parse($intake->intake_date)->format('d/m/Y') }} | Wakati: {{ $intake->feeding_time }}</p>
    </div>

    <table>
        <tr><th>Chakula</th><td>{{ $intake->feed->name }}</td></tr>
        <tr><th>Kiasi (kg)</th><td>{{ $intake->quantity }}</td></tr>
        <tr><th>Gharama (TZS)</th><td>{{ number_format($intake->cost) }}</td></tr>
        <tr><th>Maziwa Leo (L)</th><td>{{ $intake->milk_produced ?? 'Hajapimwa' }}</td></tr>
        <tr><th>FCR</th><td>{{ $intake->fcr ?? 'N/A' }}</td></tr>
        <tr><th>Tathmini</th><td><strong>{{ $intake->efficiency_grade }}</strong></td></tr>
    </table>

    <p><strong>Maelezo:</strong> {{ $intake->notes ?? 'Hakuna' }}</p>
    <p>Imetolewa: {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
