<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Maziwa</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f0f0f0; }
        .status { font-weight: bold; }
        .good { color: green; }
        .warning { color: orange; }
        .critical { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA MAZIWA</h1>
        <p><strong>Ng'ombe:</strong> {{ $yield->animal->tag_number }} - {{ $yield->animal->name ?? 'Hapana Jina' }}</p>
        <p><strong>Tarehe:</strong> {{ $yield->yield_date->format('d/m/Y') }} | <strong>Kipindi:</strong> {{ $yield->milking_session }}</p>
    </div>

    <div class="info">
        <table>
            <tr><th>Kiasi (L)</th><td>{{ $yield->quantity_liters }}</td></tr>
            <tr><th>Daraja</th><td><span class="status {{ $yield->quality_grade === 'A' ? 'good' : ($yield->quality_grade === 'Rejected' ? 'critical' : 'warning') }}">
                {{ $yield->quality_grade }}
            </span></td></tr>
            <tr><th>Mafuta (%)</th><td>{{ $yield->fat_content ?? 'Hajapimwa' }}</td></tr>
            <tr><th>Protini (%)</th><td>{{ $yield->protein_content ?? 'Hajapimwa' }}</td></tr>
            <tr><th>SCC</th><td>{{ number_format($yield->somatic_cell_count ?? 0) }} <small>/ml</small></td></tr>
            <tr><th>Joto (°C)</th><td>{{ $yield->temperature ?? 'Hajapimwa' }}</td></tr>
            <tr><th>Umeme (mS/cm)</th><td>{{ $yield->conductivity ?? 'Hajapimwa' }}</td></tr>
            <tr><th>Hatari ya Mastitis</th><td class="{{ $yield->is_mastitis_risk ? 'critical' : 'good' }}">
                {{ $yield->is_mastitis_risk ? 'NDOO' : 'Salama' }}
            </td></tr>
            <tr><th>Mapato (TZS)</th><td>{{ number_format($yield->actual_income ?? 0) }}</td></tr>
        </table>
    </div>

    <p><strong>Maelezo:</strong> {{ $yield->notes ?? 'Hakuna' }}</p>
    <p><small>Imetolewa: {{ now()->format('d/m/Y H:i') }} | Mkulima: {{ $farmer->name }}</small></p>
</body>
</html>
