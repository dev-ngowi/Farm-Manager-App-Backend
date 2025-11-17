<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Risiti ya Hatua</title>
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
        <h1>RISITI YA HATUA YA MADAKTARI</h1>
        <p><strong>Mkulima:</strong> {{ $action->diagnosis->healthReport->farmer->user->firstname }} {{ $action->diagnosis->healthReport->farmer->user->lastname }}</p>
        <p><strong>Mnyama:</strong> {{ $action->diagnosis->healthReport->animal->tag_number }} - {{ $action->diagnosis->healthReport->animal->name }}</p>
        <p><strong>Tarehe:</strong> {{ $action->action_date->format('d/m/Y') }} | {{ $action->action_time->format('H:i') }}</p>
    </div>

    <table>
        <tr><th>Hatua</th><td>{{ $action->action_type_swahili }}</td></tr>
        <tr><th>Mahali</th><td><span class="badge {{ $action->location_badge }}">{{ $action->action_location }}</span></td></tr>
        @if($action->medicine_name)
            <tr><th>Dawa</th><td>{{ $action->medicine_name }} | Kipimo: {{ $action->dosage }}</td></tr>
        @endif
        @if($action->vaccine_name)
            <tr><th>Chanjo</th><td>{{ $action->vaccine_name }} | Batch: {{ $action->vaccine_batch_number }}</td></tr>
            <tr><th>Chanjo Inayofuata</th><td>{{ $action->next_vaccination_text ?? 'Hapana' }}</td></tr>
        @endif
        <tr><th>Gharama</th><td class="total">{{ $action->cost_formatted }}</td></tr>
        <tr><th>Hali ya Malipo</th><td>{{ $action->payment_status === 'Paid' ? 'Imelipwa' : ($action->payment_status === 'Waived' ? 'Imeondolewa' : 'Inasubiri') }}</td></tr>
    </table>

    @if($action->prescription)
        <h3>Dawa ya Kununua</h3>
        <p><strong>Dawa:</strong> {{ $action->prescription->medicine_name }}<br>
           <strong>Kipimo:</strong> {{ $action->prescription->dosage }} x {{ $action->prescription->frequency }} kwa siku {{ $action->prescription->duration_days }}</p>
    @endif

    <p><small>Madaktari: Dr. {{ $action->veterinarian->user->firstname }} {{ $action->veterinarian->user->lastname }}</small></p>
</body>
</html>
