<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<title>Ripoti ya Afya - {{ $report->animal->tag_number }}</title>
<style>
    @page { margin: 1.5cm 1cm; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 12px;
        color: #000;
        line-height: 1.5;
        position: relative;
    }
    .header { text-align: center; border-bottom: 4px solid #1e40af; padding-bottom: 15px; margin-bottom: 25px; }
    .logo { width: 90px; height: auto; margin-bottom: 10px; }
    .title { font-size: 26px; font-weight: bold; color: #1e40af; margin: 10px 0; }
    .subtitle { font-size: 16px; color: #555; }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin: 25px 0;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .info-table th {
        background: #1e40af;
        color: white;
        padding: 12px;
        text-align: left;
    }
    .info-table td {
        padding: 12px;
        border: 1px solid #ddd;
        vertical-align: top;
    }
    .label {
        font-weight: bold;
        width: 35%;
        background: #f8fafc;
        color: #1e40af;
    }
    .status {
        padding: 6px 14px;
        border-radius: 25px;
        color: white;
        font-weight: bold;
        font-size: 11px;
        display: inline-block;
    }
    .emergency { background: #dc2626; }
    .high { background: #f97316; }
    .medium { background: #facc15; color: black; }
    .low { background: #6b7280; }
    .recovered { background: #16a34a; }
    .pending { background: #6366f1; }

    .photos { margin-top: 30px; page-break-inside: avoid; }
    .photos img, .photos video {
        max-width: 280px;
        height: auto;
        margin: 10px;
        border: 2px solid #ddd;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .footer {
        text-align: center;
        margin-top: 60px;
        font-size: 10px;
        color: #666;
        border-top: 1px solid #eee;
        padding-top: 15px;
    }

    /* FIXED WATERMARK - SAFE FOR PDF */
    .watermark {
        position: running(header);
        opacity: 0.08;
        font-size: 90px;
        font-weight: bold;
        color: #1e40af;
        transform: rotate(-45deg);
        pointer-events: none;
        z-index: -1;
    }
</style>
</head>
<body>

<!-- REMOVE WATERMARK FROM BODY - USE @page HEADER INSTEAD -->
@php
    \Barryvdh\DomPDF\Facade\Pdf::setOption(['isHtml5ParserEnabled' => true, 'isPhpEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
@endphp

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
        <td><span class="status {{ strtolower($report->severity) }}">{{ strtoupper($report->severity) }}</span></td>
    </tr>
    <tr>
        <td class="label">Kipaumbele</td>
        <td><span class="status {{ strtolower($report->priority) }}">{{ strtoupper($report->priority) }}</span></td>
    </tr>
    <tr>
        <td class="label">Hali ya Sasa</td>
        <td><span class="status {{ strtolower(str_replace(' ', '-', $report->status)) }}">{{ $report->status }}</span></td>
    </tr>
    @if($report->location_url)
    <tr>
        <td class="label">Mahali</td>
        <td><a href="{{ $report->location_url }}">Fungua Ramani</a></td>
    </tr>
    @endif
</table>

@if($report->diagnoses->count() > 0)
<h3 style="color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 8px;">UGONJWA ULIOGUNDULIWA</h3>
<table class="info-table">
    @foreach($report->diagnoses as $diag)
    <tr>
        <td class="label">Ugonjwa</td>
        <td>{{ $diag->disease_condition }} @if($diag->is_confirmed) <strong>(Imethibitishwa)</strong> @endif</td>
    </tr>
    <tr>
        <td class="label">Daktari</td>
        <td>{{ $diag->vet?->name ?? 'Hajaainishwa' }}</td>
    </tr>
    @endforeach
</table>
@endif

@if($report->media->count() > 0)
<div class="photos">
    <h3 style="color: #1e40af;">PICHA NA VIDEO</h3>
    @foreach($report->media as $media)
        @if(str_contains($media->mime_type, 'image'))
            <img src="{{ storage_path('app/public/' . $media->path) }}" alt="Picha ya Afya">
        @elseif(str_contains($media->mime_type, 'video'))
            <video controls style="max-width: 100%; height: auto;">
                <source src="{{ storage_path('app/public/' . $media->path) }}" type="{{ $media->mime_type }}">
            </video>
        @endif
    @endforeach
</div>
@endif

<div class="footer">
    <strong>Farm Manager App</strong> • {{ $farmer->name }} • {{ $farmer->phone }}<br>
    Imetengenezwa: {{ now()->format('d F Y') }} • {{ now()->format('H:i') }} EAT
</div>

</body>
</html>
