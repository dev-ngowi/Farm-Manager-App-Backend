<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cheti cha Chanjo</title>
    <style>
        body { font-family: DejaVu Sans; margin: 40px; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; }
        .content { margin-top: 30px; font-size: 16px; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 12px; text-align: left; }
        th { background: #f0f0f0; }
        .stamp { text-align: center; margin-top: 40px; font-style: italic; }
        .signature { margin-top: 60px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CHETI CHA CHANJO</h1>
        <p><strong>Tarehe ya Chanjo:</strong> {{ $schedule->completed_date->format('d/m/Y') }}</p>
    </div>

    <div class="content">
        <p>Hii ni kuthibitisha kwamba mnyama aliyefuatwa amechanjwa:</p>

        <table>
            <tr><th>Mnyama</th><td>{{ $schedule->animal->tag_number }} - {{ $schedule->animal->name }}</td></tr>
            <tr><th>Mmiliki</th><td>{{ $schedule->animal->farmer->user->fullname }}</td></tr>
            <tr><th>Chanjo</th><td>{{ $schedule->vaccine_name }}</td></tr>
            <tr><th>Inayokinga</th><td>{{ $schedule->disease_swahili }}</td></tr>
            <tr><th>Batch No.</th><td>{{ $schedule->batch_number ?? 'Hapana' }}</td></tr>
            <tr><th>Kipimo</th><td>{{ $schedule->dose_ml }} ml</td></tr>
            <tr><th>Njia</th><td>{{ $schedule->administration_route ?? 'Hapana' }}</td></tr>
        </table>

        <div class="stamp">
            <p><strong>Madaktari:</strong> Dr. {{ $schedule->veterinarian->user->fullname }}</p>
            <p>Kliniki: {{ $schedule->veterinarian->clinic_name }}</p>
        </div>

        <div class="signature">
            <p>_________________________</p>
            <p>Saini ya Daktari</p>
        </div>
    </div>
</body>
</html>
