<!DOCTYPE html>
<html>
<head>
    <title>Ripoti Zote Za Afya</title>
    <style>
        body { font-family: DejaVu Sans; font-size: 11px; }
        .report { page-break-after: always; border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; }
        .header { background: #1e40af; color: white; padding: 10px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #f3f4f6; }
        td, th { border: 1px solid #ddd; padding: 8px; }
    </style>
</head>
<body>
    <h1>RIPOTI ZOTE ZA AFYA • {{ now()->format('d/m/Y') }}</h1>
    <p><strong>Mkulima:</strong> {{ $farmer->name }} • {{ $farmer->phone }}</p>

    @foreach($reports as $report)
        <div class="report">
            <div class="header">Ripoti #{{ $report->health_id }} • {{ $report->animal->tag_number }}</div>
            <table>
                <tr><th>Dalili</th><td>{{ $report->symptoms }}</td></tr>
                <tr><th>Ukali</th><td>{{ $report->severity }}</td></tr>
                <tr><th>Hali</th><td>{{ $report->status }}</td></tr>
                <tr><th>Tarehe</th><td>{{ $report->report_date->format('d/m/Y') }}</td></tr>
            </table>
        </div>
    @endforeach
</body>
</html>
