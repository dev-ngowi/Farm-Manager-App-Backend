<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Uzito</title>
    <style>
        body { font-family: DejaVu Sans; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .grade { font-weight: bold; }
        .good { color: green; }
        .warning { color: orange; }
        .critical { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA UZITO</h1>
        <p><strong>Mifugo:</strong> {{ $record->animal->tag_number }} - {{ $record->animal->name ?? 'Hapana' }}</p>
        <p><strong>Tarehe:</strong> {{ $record->record_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Uzito (kg)</th><td>{{ $record->weight_kg }}</td></tr>
        <tr><th>BCS</th><td><span class="grade {{ $record->bcs_grade === 'Ideal' ? 'good' : ($record->bcs_grade === 'Emaciated' ? 'critical' : 'warning') }}">
            {{ $record->bcs_grade }} ({{ $record->body_condition_score }})
        </span></td></tr>
        <tr><th>Njia</th><td>{{ $record->measurement_method === 'Scale' ? 'Mizani' : ($record->measurement_method === 'Tape' ? 'Mipira' : 'Kwa Macho') }}</td></tr>
        <tr><th>Mipira (cm)</th><td>{{ $record->heart_girth_cm ?? 'Hajapimwa' }}</td></tr>
        <tr><th>Urefu (cm)</th><td>{{ $record->height_cm ?? 'Hajapimwa' }}</td></tr>
        <tr><th>ADG Tangu Mwisho</th><td>{{ $record->adg_since_last ?? 'N/A' }} kg/siku</td></tr>
        <tr><th>FCR</th><td>{{ $record->fcr_since_last ?? 'N/A' }}</td></tr>
        <tr><th>Gharama ya Kilo</th><td>TZS {{ number_format($record->cost_of_gain ?? 0) }}</td></tr>
        <tr><th>Thamani ya Soko</th><td>TZS {{ number_format($record->estimated_price ?? 0) }}</td></tr>
        <tr><th>Tarehe ya Kuuzwa</th><td>{{ $record->projected_sale_date }}</td></tr>
    </table>

    <p><strong>Maelezo:</strong> {{ $record->notes ?? 'Hakuna' }}</p>
    <p><strong>Imepimwa na:</strong> {{ $record->recorded_by ?? 'Hajajulikana' }}</p>
    <p><small>Imetolewa: {{ now()->format('d/m/Y H:i') }} | Mkulima: {{ $farmer->name }}</small></p>
</body>
</html>
