<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ripoti ya Faida na Hasara</title>
    <style>
        body { font-family: DejaVu Sans; margin: 20px; font-size: 14px; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 22px; }
        h2 { font-size: 18px; background: #f4f4f4; padding: 8px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .total { font-weight: bold; background: #e6f7ff; font-size: 16px; }
        .profit { color: green; }
        .loss { color: red; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 40px; font-size: 12px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RIPOTI YA FAIDA NA HASARA</h1>
        <p><strong>Mkulima:</strong> {{ $farmer->user->firstname }} {{ $farmer->user->lastname }} | {{ $farmer->farm_name }}</p>
        <p><strong>Kipindi:</strong> {{ $report->title }}</p>
        <p><strong>Imetolewa:</strong> {{ $report->generated_at }}</p>
    </div>

    <!-- INCOME -->
    <h2>Mapato (Income)</h2>
    <table>
        <tr><th>Aina</th><th class="text-right">Kiasi (TZS)</th></tr>
        <tr><td>Maziwa</td><td class="text-right">{{ number_format($report->income->milk, 0) }}</td></tr>
        <tr><td>Uuzaji wa Wanyama</td><td class="text-right">{{ number_format($report->income->sales, 0) }}</td></tr>
        <tr><td>Mengineyo</td><td class="text-right">{{ number_format($report->income->other, 0) }}</td></tr>
        <tr class="total"><td><strong>Jumla ya Mapato</strong></td><td class="text-right"><strong>{{ number_format($report->income->total, 0) }}</strong></td></tr>
    </table>

    <!-- EXPENSES -->
    <h2>Gharama (Expenses)</h2>
    <table>
        <tr><th>Aina ya Gharama</th><th class="text-right">Kiasi (TZS)</th><th class="text-right">% ya Jumla</th></tr>
        @foreach($report->expenses->by_category as $cat)
            <tr>
                <td>{{ $cat->category }}</td>
                <td class="text-right">{{ number_format($cat->amount, 0) }}</td>
                <td class="text-right">{{ $cat->percentage }}%</td>
            </tr>
        @endforeach
        <tr class="total">
            <td><strong>Jumla ya Gharama</strong></td>
            <td class="text-right"><strong>{{ number_format($report->expenses->total, 0) }}</strong></td>
            <td class="text-right">100%</td>
        </tr>
    </table>

    <!-- PROFIT/LOSS -->
    <h2>Faida au Hasara</h2>
    <table>
        <tr>
            <td><strong>Jumla ya Mapato</strong></td>
            <td class="text-right"><strong>{{ number_format($report->income->total, 0) }}</strong></td>
        </tr>
        <tr>
            <td><strong>Punguza: Jumla ya Gharama</strong></td>
            <td class="text-right"><strong>- {{ number_format($report->expenses->total, 0) }}</strong></td>
        </tr>
        <tr class="{{ $report->kpis->gross_profit >= 0 ? 'profit' : 'loss' }} total">
            <td><strong>{{ $report->kpis->net_profit_status }}</strong></td>
            <td class="text-right"><strong>{{ number_format($report->kpis->gross_profit, 0) }}</strong></td>
        </tr>
    </table>

    <!-- KPIs -->
    <h2>Vipimo vya Biashara (KPIs)</h2>
    <table>
        <tr><td>Bei ya Gharama kwa Lita</td><td class="text-right">{{ $report->kpis->cost_per_liter ? 'TZS ' . number_format($report->kpis->cost_per_liter, 2) : 'N/A' }}</td></tr>
        <tr><td>Asilimia ya Faida</td><td class="text-right">{{ $report->kpis->profit_margin_percent }}%</td></tr>
        <tr><td>Gharama dhidi ya Mapato</td><td class="text-right">{{ $report->kpis->income_vs_expense }}%</td></tr>
    </table>

    <div class="footer">
        <p>Ripoti hii imetengenezwa kiotomatiki na mfumo wa usimamizi wa shamba.</p>
    </div>
</body>
</html>
