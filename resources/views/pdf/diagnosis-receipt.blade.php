<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Utambuzi</title>
    <style>
        body { font-family: DejaVu Sans; margin: 30px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .total { font-weight: bold; font-size: 1.2em; }
        .grave { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RISITI YA UTAMBUZI NA MATIBABU</h1>
        <p><strong>Mkulima:</strong> {{ $diagnosis->healthReport->farmer->user->firstname }} {{ $diagnosis->healthReport->farmer->user->lastname }}</p>
        <p><strong>Mnyama:</strong> {{ $diagnosis->healthReport->animal->tag_number }} - {{ $diagnosis->healthReport->animal->name }}</p>
        <p><strong>Tarehe:</strong> {{ $diagnosis->diagnosis_date->format('d/m/Y') }}</p>
    </div>

    <h2>Utambuzi</h2>
    <table>
        <tr><th>Magonjwa Yanayoshukiwa</th><td>{{ $diagnosis->swahili_disease }}</td></tr>
        <tr><th>Utabiri (Prognosis)</th><td class="{{ $diagnosis->prognosis === 'Grave' ? 'grave' : '' }}">{{ $diagnosis->prognosis }}</td></tr>
        <tr><th>Muda wa Kupona</th><td>{{ $diagnosis->recovery_text }}</td></tr>
        <tr><th>Maelezo</th><td>{{ $diagnosis->diagnosis_notes }}</td></tr>
    </table>

    <h2>Hatua Zilizochukuliwa</h2>
    @foreach($diagnosis->vetActions as $action)
        <p><strong>{{ $action->action_type_swahili }}</strong> - {{ $action->action_location }} @ {{ $action->action_date->format('d/m/Y') }}</p>
        @if($action->medicine_name)
            <p><strong>Dawa:</strong> {{ $action->medicine_name }} | <strong>Kipimo:</strong> {{ $action->dosage }}</p>
        @endif
        @if($action->prescription)
            <p><strong>Dawa ya Kununua:</strong> {{ $action->prescription->medicine_name }} ({{ $action->prescription->dosage }} x {{ $action->prescription->frequency }} kwa siku {{ $action->prescription->duration_days }})</p>
        @endif
        <p><strong>Gharama:</strong> {{ $action->cost_formatted }}</p>
    @endforeach

    @if($diagnosis->follow_up_required)
        <p><strong>Ufuatiliaji:</strong> Tarehe {{ $diagnosis->follow_up_date?->format('d/m/Y') }}</p>
    @endif

    <p><small>Daktari: Dr. {{ $diagnosis->veterinarian->user->firstname }} {{ $diagnosis->veterinarian->user->lastname }}</small></p>
</body>
</html>
