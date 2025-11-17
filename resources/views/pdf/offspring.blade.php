<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Ndama</title>
    <style>
        body { font-family: DejaVu Sans; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .status { font-weight: bold; }
        .good { color: green; }
        .warning { color: orange; }
        .critical { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA NDAMA</h1>
        <p><strong>Tag:</strong> {{ $offspring->animal_tag }} | <strong>Jinsia:</strong> {{ $offspring->gender === 'Male' ? 'Dume' : 'Jike' }}</p>
        <p><strong>Tarehe ya Kuzaliwa:</strong> {{ $offspring->birth->birth_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Mama (Dam)</th><td>{{ $offspring->dam()->first()->tag_number }} - {{ $offspring->dam()->first()->name ?? 'Hapana' }}</td></tr>
        <tr><th>Baba (Sire)</th><td>{{ $offspring->sire()->first()->tag_number ?? 'Hajajulikana' }}</td></tr>
        <tr><th>Uzito wa Kuzaliwa</th><td>{{ $offspring->weight_at_birth_kg }} kg</td></tr>
        <tr><th>Uzito wa Sasa</th><td>{{ $offspring->current_weight ?? 'Hajapimwa' }} kg</td></tr>
        <tr><th>ADG</th><td>{{ $offspring->adg_since_birth ?? 'N/A' }} kg/siku</td></tr>
        <tr><th>Colostrum</th><td><span class="status {{ $offspring->colostrum_status === 'Good' ? 'good' : 'critical' }}">
            {{ $offspring->colostrum_status }}
        </span></td></tr>
        <tr><th>Hali ya Afya</th><td><span class="status {{ $offspring->health_status === 'Healthy' ? 'good' : ($offspring->health_status === 'Deceased' ? 'critical' : 'warning') }}">
            {{ $offspring->health_status }}
        </span></td></tr>
        <tr><th>Thamani ya Soko</th><td>TZS {{ number_format($offspring->market_value_estimate ?? 0) }}</td></tr>
        <tr><th>Mapato Yote</th><td>TZS {{ number_format($offspring->total_revenue) }}</td></tr>
        <tr><th>Faida Halisi</th><td>TZS {{ number_format($offspring->net_profit) }}</td></tr>
    </table>

    @if($offspring->is_twin)
        <p><strong>Pacha:</strong> <span class="{{ $offspring->twin_survival_bonus === 'Both Survived' ? 'good' : 'warning' }}">
            {{ $offspring->twin_survival_bonus }}
        </span></p>
    @endif

    <p><strong>Maelezo:</strong> {{ $offspring->notes ?? 'Hakuna' }}</p>
    <p><small>Imetolewa: {{ now()->format('d/m/Y H:i') }} | Mkulima: {{ $farmer->name }}</small></p>
</body>
</html>
