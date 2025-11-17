<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Uzalishaji</title>
    <style>
        body { font-family: DejaVu Sans; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .grade { font-weight: bold; }
        .excellent { color: green; }
        .good { color: #1a8c1a; }
        .average { color: orange; }
        .poor { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA UZALISHAJI</h1>
        <p><strong>Ng'ombe:</strong> {{ $factor->animal->tag_number }} - {{ $factor->animal->name ?? 'Hapana' }}</p>
        <p><strong>Kipindi:</strong> {{ $factor->period_start->format('d/m/Y') }} - {{ $factor->period_end->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Muda (Siku)</th><td>{{ $factor->days_in_period }}</td></tr>
        <tr><th>Maziwa Yote (L)</th><td>{{ $factor->total_milk_produced_liters }}</td></tr>
        <tr><th>Wastani wa Kila Siku</th><td>{{ $factor->avg_daily_milk_liters }} L</td></tr>
        <tr><th>Chakula Kilichotumiwa</th><td>{{ $factor->total_feed_consumed_kg }} kg</td></tr>
        <tr><th>FCR (Chakula:Maziwa)</th><td><span class="grade {{ $factor->efficiency_grade === 'World Class' ? 'excellent' : ($factor->efficiency_grade === 'Poor' ? 'poor' : 'average') }}">
            {{ $factor->feed_to_milk_ratio }}
        </span></td></tr>
        <tr><th>Mapato ya Maziwa</th><td>TZS {{ number_format($factor->income_from_milk) }}</td></tr>
        <tr><th>Gharama ya Chakula</th><td>TZS {{ number_format($factor->feed_cost) }}</td></tr>
        <tr><th>Gharama Zingine</th><td>TZS {{ number_format($factor->other_costs) }}</td></tr>
        <tr><th>Jumla ya Gharama</th><td>TZS {{ number_format($factor->total_cost) }}</td></tr>
        <tr><th>Faida Halisi</th><td>TZS {{ number_format($factor->net_profit) }}</td></tr>
        <tr><th>Faida kwa Siku</th><td>TZS {{ number_format($factor->profit_per_day) }}</td></tr>
        <tr><th>Tathmini</th><td><strong class="{{ $factor->efficiency_grade === 'World Class' ? 'excellent' : 'poor' }}">
            {{ $factor->efficiency_grade }}
        </strong></td></tr>
        <tr><th>Pendekezo</th><td><strong>
            {{ $factor->culling_recommendation === 'KEEP' ? 'WEKA' : $factor->culling_recommendation }}
        </strong></td></tr>
    </table>

    <p><strong>Maelezo:</strong> {{ $factor->notes ?? 'Hakuna' }}</p>
    <p><small>Imetolewa: {{ now()->format('d/m/Y H:i') }} | Mkulima: {{ $farmer->name }}</small></p>
</body>
</html>
