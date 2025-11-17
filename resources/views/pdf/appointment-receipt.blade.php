<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Miadi</title>
    <style>
        body { font-family: DejaVu Sans; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .qr { text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RISITI YA MIADI</h1>
        <p><strong>Mkulima:</strong> {{ $appointment->farmer->user->fullname }}</p>
        <p><strong>Daktari:</strong> Dr. {{ $appointment->veterinarian->user->fullname }}</p>
    </div>

    <table>
        <tr><th>Aina</th><td>{{ $appointment->type_swahili }}</td></tr>
        <tr><th>Tarehe & Saa</th><td>{{ $appointment->full_date_time }}</td></tr>
        <tr><th>Mahali</th><td>{{ $appointment->location_text }}</td></tr>
        <tr><th>Mnyama</th><td>{{ $appointment->animal?->tag_number ?? 'Hapana' }} - {{ $appointment->animal?->name ?? '' }}</td></tr>
        <tr><th>Hali</th><td>{{ $appointment->status_swahili }}</td></tr>
    </table>

    <div class="qr">
        <p><strong>QR Code ya Kuingia:</strong></p>
        <img src="{{ $appointment->check_in_qr }}" alt="QR Code" width="150">
    </div>

    <p><small>Maelezo: {{ $appointment->notes ?? 'Hapana' }}</small></p>
</body>
</html>
