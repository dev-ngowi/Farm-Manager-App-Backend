<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Ukaguzi wa Mimba</title>
    <style>
        body { font-family: DejaVu Sans; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .good { color: green; font-weight: bold; }
        .warning { color: orange; }
        .critical { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA UKAGUZI WA MIMBA</h1>
        <p><strong>Mama (Dam):</strong> {{ $check->breeding->dam->tag_number }} - {{ $check->breeding->dam->name ?? 'Hapana' }}</p>
        <p><strong>Tarehe ya Kupandisha:</strong> {{ $check->breeding->breeding_date->format('d/m/Y') }}</p>
        <p><strong>Tarehe ya Ukaguzi:</strong> {{ $check->check_date->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr><th>Njia</th><td>{{ $check->method === 'Ultrasound' ? 'Ultrasound' : ($check->method === 'Palpation' ? 'Kupapasa' : $check->method) }}</td></tr>
        <tr><th>Matokeo</th><td><span class="{{ $check->result === 'Pregnant' ? 'good' : 'warning' }}">{{ $check->result === 'Pregnant' ? 'Mimba' : ($check->result === 'Not Pregnant' ? 'Hapana Mimba' : 'Haijulikani') }}</span></td></tr>
        <tr><th>Fetus</th><td>{{ $check->fetus_prediction }} ({{ $check->fetus_count ?? 'N/A' }})</td></tr>
        <tr><th>Tarehe Inayotarajiwa</th><td>{{ $check->expected_delivery_date?->format('d/m/Y') ?? 'Haijapangwa' }}</td></tr>
        <tr><th>Siku za Mimba</th><td>{{ $check->days_after_breeding }} siku</td></tr>
        <tr><th>Usahihi</th><td><span class="{{ $check->accuracy_grade === 'Accurate' ? 'good' : ($check->accuracy_grade === 'Inaccurate' ? 'warning' : '') }}">{{ $check->accuracy_grade }}</span></td></tr>
    </table>

    <p><strong>Maelezo:</strong> {{ $check->notes ?? 'Hakuna' }}</p>
    <p><strong>Daktari:</strong> {{ $check->vet?->user?->firstname ?? 'Hajajulikana' }} {{ $check->vet?->user?->lastname ?? '' }}</p>
    <p><small>Imetolewa: {{ now()->format('d/m/Y H:i') }} | Mkulima: {{ $farmer->name }}</small></p>
</body>
</html>
