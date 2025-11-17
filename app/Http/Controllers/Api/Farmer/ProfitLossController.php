<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MilkYieldRecord;
use App\Models\Sale;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ProfitLossController extends Controller
{
    // =================================================================
    // REPORT: Generate P&L for period
    // =================================================================
    public function report(Request $request)
    {
        $farmer = $request->user()->farmer;
        $period = $request->get('period', 'month'); // month, quarter, year, custom
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        $start = $end = null;

        switch ($period) {
            case 'month':
                $start = Carbon::create($year, $month, 1)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $title = "Ripoti ya Faida/Hasara – " . $start->translatedFormat('F Y');
                break;
            case 'quarter':
                $quarter = $request->get('quarter', ceil($month / 3));
                $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfMonth();
                $end = $start->copy()->addMonths(2)->endOfMonth();
                $title = "Ripoti ya Robo Mwaka $quarter – $year";
                break;
            case 'year':
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();
                $title = "Ripoti ya Mwaka – $year";
                break;
            case 'custom':
                $start = Carbon::parse($request->start_date);
                $end = Carbon::parse($request->end_date);
                $title = "Ripoti Maalum: " . $start->format('d/m/Y') . " - " . $end->format('d/m/Y');
                break;
            default:
                return response()->json(['status' => 'error', 'message' => 'Kipindi kisichojulikana'], 400);
        }

        // === INCOME ===
        $milkIncome = MilkYieldRecord::where('farmer_id', $farmer->id)
            ->whereBetween('yield_date', [$start, $end])
            ->sum(DB::raw('quantity_liters * price_per_liter'));

        $salesIncome = Sale::where('farmer_id', $farmer->id)
            ->whereBetween('sale_date', [$start, $end])
            ->sum('total_amount');

        $otherIncome = 0; // Future: grants, subsidies, etc.

        $totalIncome = $milkIncome + $salesIncome + $otherIncome;

        // === EXPENSES ===
        $totalExpenses = Expense::where('farmer_id', $farmer->id)
            ->whereBetween('expense_date', [$start, $end])
            ->where('status', 'Approved')
            ->sum('amount');

        // === CATEGORY BREAKDOWN ===
        $categories = Expense::where('farmer_id', $farmer->id)
            ->whereBetween('expense_date', [$start, $end])
            ->where('status', 'Approved')
            ->with('category')
            ->get()
            ->groupBy('category.category_name')
            ->map(fn($group) => $group->sum('amount'))
            ->sortDesc();

        // === KPIs ===
        $grossProfit = $totalIncome - $totalExpenses;

        $profitMargin = $totalIncome > 0
            ? round(($grossProfit / $totalIncome) * 100, 1)
            : 0;

        $litersProduced = MilkYieldRecord::where('farmer_id', $farmer->id)
            ->whereBetween('yield_date', [$start, $end])
            ->sum('quantity_liters');

        $costPerLiter = ($litersProduced > 0)
            ? round($totalExpenses / $litersProduced, 2)
            : null;

        $report = [
            'period' => $period,
            'title' => $title,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'generated_at' => now()->format('d/m/Y H:i'),
            'income' => [
                'milk' => round($milkIncome, 2),
                'sales' => round($salesIncome, 2),
                'other' => round($otherIncome, 2),
                'total' => round($totalIncome, 2),
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'by_category' => $categories->map(fn($amount, $name) => [
                    'category' => $name,
                    'amount' => round($amount, 2),
                    'percentage' => $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0
                ])->values()
            ],
            'kpis' => [
                'gross_profit' => round($grossProfit, 2),
                'profit_margin_percent' => $profitMargin,
                'net_profit_status' => $grossProfit >= 0 ? 'Faida' : 'Hasara',
                'cost_per_liter' => $costPerLiter,
                'income_vs_expense' => $totalIncome > 0 ? round(($totalExpenses / $totalIncome) * 100, 1) : null,
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $report
        ]);
    }

    // =================================================================
    // CHART: JSON for Chart.js (Monthly Trend)
    // =================================================================
    public function chartData(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $year = $request->get('year', now()->year);

        $months = collect(range(1, 12))->map(function ($m) use ($year, $farmerId) {
            $start = Carbon::create($year, $m, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $income = MilkYieldRecord::where('farmer_id', $farmerId)
                    ->whereBetween('yield_date', [$start, $end])
                    ->sum(DB::raw('quantity_liters * price_per_liter')) +
                Sale::where('farmer_id', $farmerId)
                    ->whereBetween('sale_date', [$start, $end])
                    ->sum('total_amount');

            $expenses = Expense::where('farmer_id', $farmerId)
                ->whereBetween('expense_date', [$start, $end])
                ->where('status', 'Approved')
                ->sum('amount');

            return [
                'month' => $start->translatedFormat('M'),
                'income' => round($income),
                'expenses' => round($expenses),
                'profit' => round($income - $expenses),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'labels' => $months->pluck('month'),
                'income' => $months->pluck('income'),
                'expenses' => $months->pluck('expenses'),
                'profit' => $months->pluck('profit'),
            ]
        ]);
    }

    // =================================================================
    // PDF: Download P&L Report
    // =================================================================
    public function downloadPdf(Request $request)
    {
        $report = $this->report($request)->getData()->data;
        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.profit-loss', compact('report', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Ripoti-Faida-Hasara-" . now()->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $thisMonth = Expense::where('farmer_id', $farmerId)->thisMonth()->where('status', 'Approved')->sum('amount');
        $milkIncome = MilkYieldRecord::where('farmer_id', $farmerId)->thisMonth()->sum(DB::raw('quantity_liters * price_per_liter'));
        $profit = $milkIncome - $thisMonth;

        return response()->json([
            'status' => 'success',
            'data' => [
                'monthly_profit' => round($profit, 2),
                'profit_trend' => $profit >= 0 ? 'up' : 'down',
                'break_even_liters' => $thisMonth > 0 && $milkIncome > 0
                    ? round($thisMonth / (MilkYieldRecord::where('farmer_id', $farmerId)->avg('price_per_liter') ?: 1))
                    : null,
                'top_income_source' => $milkIncome > 0 ? 'Maziwa' : 'Uuzaji',
            ]
        ]);
    }
}
