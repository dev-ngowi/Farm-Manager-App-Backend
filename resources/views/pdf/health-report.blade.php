<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripoti ya Afya - {{ $report->animal->tag_number }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { width: 80px; height: auto; }
        .title { font-size: 24px; font-weight: bold; color: #1e40af; margin: 10px 0; }
        .subtitle { font-size: 16px; color: #555; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table th { background: #1e40af; color: white; padding: 10px; text-align: left; }
        .info-table td { padding: 10px; border: 1px solid #ddd; vertical-align: top; }
        .label { font-weight: bold; width: 30%; background: #f3f4f6; }
        .status { padding: 5px 10px; border-radius: 20px; color: white; font-weight: bold; }
        .emergency { background: #dc2626; }
        .high { background: #f97316; }
        .medium { background: #facc15; color: black; }
        .low { background: #6b7280; }
        .recovered { background: #16a34a; }
        .pending { background: #6366f1; }
        .photos { margin-top: 20px; }
        .photos img, .photos video { max-width: 300px; margin: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .footer { text-align: center; margin-top: 50px; font-size: 10px; color: #888; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.1); pointer-events: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="watermark">FARM MANAGER</div>

    <div class="header">
        @if($farmer->logo)
            <img src="{{ public_path('storage/' . $farmer->logo) }}" class="logo" alt="Logo">
        @endif
        <div class="title">RIPOTI YA AFYA YA MFUGO</div>
        <div class="subtitle">Health Report • {{ now()->format('d/m/Y H:i') }} EAT</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Mifugo ID</td>
            <td>{{ $report->animal->tag_number }} - {{ $report->animal->name ?? 'Hajapewa Jina' }}</td>
        </tr>
        <tr>
            <td class="label">Dalili</td>
            <td>{{ $report->symptoms }}</td>
        </tr>
        <tr>
            <td class="label">Tarehe ya Dalili</td>
            <td>{{ $report->symptom_onset_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Ukali</td>
            <td><span class="status {{ strtolower($report->severity) }}">{{ $report->severity }}</span></td>
        </tr>
        <tr>
            <td class="label">Kipaumbele</td>
            <td><span class="status {{ strtolower($report->priority) }}">{{ $report->priority }}</span></td>
        </tr>
        <tr>
            <td class="label">Hali ya Sasa</td>
            <td><span class="status {{ strtolower(str_replace(' ', '-', $report->status)) }}">{{ $report->status }}</span></td>
        </tr>
        @if($report->location_url)
        <tr>
            <td class="label">Mahali</td>
            <td><a href="{{ $report->location_url }}" target="_blank">Fungua Ramani</a></td>
        </tr>
        @endif
    </table>

    @if($report->diagnoses->count() > 0)
        <h3>Ugonjwa Uliogunduliwa</h3>
        <table class="info-table">
            @foreach($report->diagnoses as $diag)
            <tr>
                <td class="label">Ugonjwa</td>
                <td>{{ $diag->disease_condition }} @if($diag->is_confirmed) (Imethibitishwa) @endif</td>
            </tr>
            <tr>
                <td class="label">Daktari</td>
                <td>{{ $diag->vet?->name ?? 'Hajagunduliwa' }}</td>
            </tr>
            @endforeach
        </table>
    @endif

    @if($report->media->count() > 0)
        <div class="photos">
            <h3>Picha na Video</h3>
            @foreach($report->media as $media)
                @if(str_contains($media->mime_type, 'image'))
                    <img src="{{ $media->getUrl() }}" alt="Health Photo">
                @elseif(str_contains($media->mime_type, 'video'))
                    <video controls><source src="{{ $media->getUrl() }}" type="{{ $media->mime_type }}"></video>
                @endif
            @endforeach
        </div>
    @endif

    <div class="footer">
        Ripoti hii imetengenezwa na <strong>Farm Manager App</strong> • {{ $farmer->name }} • {{ $farmer->phone }}<br>
        Tarehe: {{ now()->format('d F Y') }} • Saa: {{ now()->format('H:i') }} EAT
    </div>
</body>
</html>
