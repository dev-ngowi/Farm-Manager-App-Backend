<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Ripoti ya Kuzaliwa - {{ $birth->breeding->dam->tag_number }}</title>
    <style>
        @page { margin: 1.5cm; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.6;
            background: white;
        }
        .header {
            text-align: center;
            border-bottom: 4px solid #1e40af;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .logo {
            width: 90px;
            height: auto;
            margin-bottom: 10px;
            border-radius: 50%;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #1e40af;
            margin: 10px 0;
        }
        .subtitle {
            font-size: 16px;
            color: #555;
            font-style: italic;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .info-table th {
            background: #1e40af;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .info-table td {
            padding: 12px;
            border: 1px solid #e5e7eb;
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
        .natural { background: #16a34a; }
        .assisted { background: #f97316; }
        .cesarean { background: #dc2626; }
        .excellent { background: #16a34a; }
        .good { background: #84cc16; }
        .fair { background: #facc15; color: black; }
        .poor { background: #f97316; }
        .critical { background: #dc2626; }

        .offspring-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .offspring-table th {
            background: #1e40af;
            color: white;
            padding: 10px;
        }
        .offspring-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .offspring-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .photos {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .photos img {
            max-width: 180px;
            height: auto;
            margin: 10px;
            border: 2px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 90px;
            color: rgba(30, 64, 175, 0.08);
            font-weight: bold;
            pointer-events: none;
            z-index: -1;
        }
    </style>
</head>
<body>

<div class="watermark">FARM MANAGER</div>

<div class="header">
    @if($farmer->logo)
        <img src="{{ public_path('storage/' . $farmer->logo) }}" class="logo" alt="Logo">
    @endif
    <div class="title">RIPOTI YA KUZALIWA</div>
    <div class="subtitle">Birth Record • {{ now()->format('d/m/Y H:i') }} EAT</div>
</div>

<table class="info-table">
    <tr>
        <td class="label">Mama (Dam)</td>
        <td>{{ $birth->breeding->dam->tag_number }} - {{ $birth->breeding->dam->name ?? 'Hajapewa Jina' }}</td>
    </tr>
    <tr>
        <td class="label">Baba (Sire)</td>
        <td>{{ $birth->breeding->sire?->tag_number ?? 'Hajajulikana' }} - {{ $birth->breeding->sire?->name ?? '' }}</td>
    </tr>
    <tr>
        <td class="label">Tarehe ya Kuzaliwa</td>
        <td>{{ $birth->birth_date->format('d/m/Y') }} • {{ \Carbon\Carbon::parse($birth->birth_time)->format('H:i') }}</td>
    </tr>
    <tr>
        <td class="label">Aina ya Kuzaliwa</td>
        <td>
            <span class="status {{ strtolower(str_replace(' ', '-', $birth->birth_type)) }}">
                {{ $birth->birth_type === 'Natural' ? 'Asilia' : ($birth->birth_type === 'Assisted' ? 'Kusaidiwa' : 'Upasuaji') }}
            </span>
        </td>
    </tr>
    <tr>
        <td class="label">Hali ya Mama</td>
        <td>
            <span class="status {{ strtolower($birth->dam_condition) }}">
                {{ $birth->dam_condition === 'Excellent' ? 'Bora' : ($birth->dam_condition === 'Good' ? 'Nzuri' : ($birth->dam_condition === 'Fair' ? 'Wastani' : ($birth->dam_condition === 'Poor' ? 'Mbaya' : 'Hatarini'))) }}
            </span>
        </td>
    </tr>
    <tr>
        <td class="label">Daktari</td>
        <td>{{ $birth->vet?->name ?? 'Hakuna' }} @if($birth->vet?->phone) • {{ $birth->vet->phone }} @endif</td>
    </tr>
    <tr>
        <td class="label">Matatizo</td>
        <td>{{ $birth->complications ?? 'Hakuna' }}</td>
    </tr>
    <tr>
        <td class="label">Jumla ya Ndama</td>
        <td><strong>{{ $birth->total_offspring }}</strong> (Hai: {{ $birth->live_births }} • Waliofariki: {{ $birth->stillbirths }})</td>
    </tr>
</table>

<h3 style="color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 8px;">ORODHA YA NDAMA</h3>
<table class="offspring-table">
    <thead>
        <tr>
            <th>#</th>
            <th>ID</th>
            <th>Jina</th>
            <th>Jinsia</th>
            <th>Uzito (kg)</th>
            <th>Hali</th>
        </tr>
    </thead>
    <tbody>
        @forelse($birth->offspringRecords as $index => $offspring)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $offspring->offspring->tag_number }}</td>
                <td>{{ $offspring->offspring->name ?? 'Hajapewa' }}</td>
                <td>{{ $offspring->offspring->sex === 'Male' ? 'Mwanaume' : 'Mwanamke' }}</td>
                <td>{{ number_format($offspring->weight_at_birth_kg, 2) }}</td>
                <td>
                    <span class="status {{ $offspring->health_status === 'Healthy' ? 'excellent' : ($offspring->health_status === 'Weak' ? 'fair' : 'critical') }}">
                        {{ $offspring->health_status === 'Healthy' ? 'Mzima' : ($offspring->health_status === 'Weak' ? 'Dhaifu' : 'Amefariki') }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#999;">Hakuna ndama zilizosajiliwa</td></tr>
        @endforelse
    </tbody>
</table>

@if($birth->offspringRecords->where('offspring.photo', '!=', null)->count() > 0)
<div class="photos">
    <h3 style="color: #1e40af;">PICHA ZA NDAMA</h3>
    @foreach($birth->offspringRecords as $offspring)
        @if($offspring->offspring && $offspring->offspring->photo && file_exists(public_path('storage/' . $offspring->offspring->photo)))
            <img src="{{ public_path('storage/' . $offspring->offspring->photo) }}" alt="Picha ya {{ $offspring->offspring->tag_number }}">
        @endif
    @endforeach
</div>
@endif

<div class="footer">
    <strong>Farm Manager App</strong> • {{ $farmer->name }} • {{ $farmer->phone }}<br>
    Imetengenezwa: {{ now()->format('d F Y') }} • {{ now()->format('H:i') }} EAT •
    <em>Ripoti hii ni ya rasmi na imehifadhiwa kwenye mfumo</em>
</div>

</body>
</html>
