<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Livestock;
use App\Models\BreedingRecord;
use App\Models\MilkYieldRecord;
use App\Models\Income;
use App\Models\Expense;
use App\Models\HealthReport;
use App\Models\VaccinationSchedule;
use App\Models\FeedStock;
use App\Models\VetAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard summary
     */
    public function index(Request $request)
    {
        $farmer = $request->user()->farmer;

        if (!$farmer) {
            return response()->json([
                'success' => false,
                'message' => 'Farmer profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $this->getOverviewStats($farmer),
                'livestock' => $this->getLivestockStats($farmer),
                'financial' => $this->getFinancialStats($farmer),
                'production' => $this->getProductionStats($farmer),
                'health' => $this->getHealthStats($farmer),
                'breeding' => $this->getBreedingStats($farmer),
                'alerts' => $this->getAlerts($farmer),
                'trends' => $this->getTrends($farmer),
            ]
        ]);
    }

    /**
     * Overview Statistics
     */
    private function getOverviewStats(Farmer $farmer)
    {
        $totalAnimals = $farmer->livestock()->count();
        $activeAnimals = $farmer->livestock()->active()->count();

        return [
            'total_animals' => $totalAnimals,
            'active_animals' => $activeAnimals,
            'total_land_acres' => (float) $farmer->total_land_acres,
            'experience_years' => $farmer->years_experience,
            'experience_level' => $farmer->experience_level,
            'farm_name' => $farmer->farm_name,
            'location' => $farmer->full_address,
            'pending_requests' => $farmer->pending_requests_count,
            'profile_completion' => $this->calculateProfileCompletion($farmer),
        ];
    }

    /**
     * Livestock Statistics
     */
    private function getLivestockStats(Farmer $farmer)
    {
        $livestock = $farmer->livestock();

        return [
            'total' => $livestock->count(),
            'by_status' => [
                'active' => $livestock->active()->count(),
                'sold' => $livestock->sold()->count(),
                'deceased' => $livestock->deceased()->count(),
                'quarantine' => $livestock->quarantine()->count(),
            ],
            'by_sex' => [
                'male' => $livestock->where('sex', 'Male')->count(),
                'female' => $livestock->where('sex', 'Female')->count(),
            ],
            'by_breed' => $livestock->select('breed', DB::raw('count(*) as count'))
                ->groupBy('breed')
                ->get()
                ->pluck('count', 'breed'),
            'milking_cows' => $farmer->milking_cows_count,
            'pregnant_cows' => $this->getPregnantCount($farmer),
            'average_age_months' => $this->getAverageAge($farmer),
            'top_earner' => $this->formatAnimal($farmer->top_earner),
            'costliest_animal' => $this->formatAnimal($farmer->costliest_animal),
        ];
    }

    /**
     * Financial Statistics
     */
    private function getFinancialStats(Farmer $farmer)
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $lastMonth = now()->subMonth();

        // Current month
        $incomeThisMonth = $farmer->income()->thisMonth()->sum('amount');
        $expensesThisMonth = $farmer->expenses()->thisMonth()->sum('amount');
        $profitThisMonth = $incomeThisMonth - $expensesThisMonth;

        // Last month
        $incomeLastMonth = $farmer->income()
            ->whereMonth('income_date', $lastMonth->month)
            ->whereYear('income_date', $lastMonth->year)
            ->sum('amount');
        $expensesLastMonth = $farmer->expenses()
            ->whereMonth('expense_date', $lastMonth->month)
            ->whereYear('expense_date', $lastMonth->year)
            ->sum('amount');
        $profitLastMonth = $incomeLastMonth - $expensesLastMonth;

        // Year to date
        $incomeYTD = $farmer->income()->thisYear()->sum('amount');
        $expensesYTD = $farmer->expenses()->thisYear()->sum('amount');
        $profitYTD = $incomeYTD - $expensesYTD;

        // Today
        $todayIncome = $farmer->income()
            ->whereDate('income_date', today())
            ->sum('amount');
        $todayExpenses = $farmer->expenses()
            ->whereDate('expense_date', today())
            ->sum('amount');

        return [
            'today' => [
                'income' => (float) $todayIncome,
                'expenses' => (float) $todayExpenses,
                'profit' => (float) ($todayIncome - $todayExpenses),
                'milk_income' => (float) $farmer->today_milk_income,
            ],
            'this_month' => [
                'income' => (float) $incomeThisMonth,
                'expenses' => (float) $expensesThisMonth,
                'profit' => (float) $profitThisMonth,
                'profit_margin' => $incomeThisMonth > 0 ? round(($profitThisMonth / $incomeThisMonth) * 100, 1) : 0,
            ],
            'last_month' => [
                'income' => (float) $incomeLastMonth,
                'expenses' => (float) $expensesLastMonth,
                'profit' => (float) $profitLastMonth,
            ],
            'year_to_date' => [
                'income' => (float) $incomeYTD,
                'expenses' => (float) $expensesYTD,
                'profit' => (float) $profitYTD,
                'roi' => $expensesYTD > 0 ? round(($profitYTD / $expensesYTD) * 100, 1) : 0,
            ],
            'comparisons' => [
                'income_growth' => $this->calculateGrowth($incomeThisMonth, $incomeLastMonth),
                'expense_growth' => $this->calculateGrowth($expensesThisMonth, $expensesLastMonth),
                'profit_growth' => $this->calculateGrowth($profitThisMonth, $profitLastMonth),
            ],
            'breakdown' => [
                'income_by_category' => $this->getIncomeByCategory($farmer),
                'expenses_by_category' => $this->getExpensesByCategory($farmer),
                'top_expenses' => $this->getTopExpenses($farmer),
            ],
        ];
    }

    /**
     * Production Statistics
     */
    private function getProductionStats(Farmer $farmer)
    {
        $milkToday = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->whereDate('yield_date', today())
            ->sum('quantity_liters');

        $milkThisMonth = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->thisMonth()
            ->sum('quantity_liters');

        $milkLastMonth = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->whereMonth('yield_date', now()->subMonth()->month)
            ->whereYear('yield_date', now()->subMonth()->year)
            ->sum('quantity_liters');

        $avgDailyYield = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->where('yield_date', '>=', now()->subDays(30))
            ->avg('quantity_liters');

        return [
            'milk' => [
                'today' => (float) $milkToday,
                'this_month' => (float) $milkThisMonth,
                'last_month' => (float) $milkLastMonth,
                'average_daily' => (float) round($avgDailyYield ?? 0, 2),
                'growth' => $this->calculateGrowth($milkThisMonth, $milkLastMonth),
                'per_cow_average' => $farmer->milking_cows_count > 0
                    ? round($milkThisMonth / $farmer->milking_cows_count, 2)
                    : 0,
            ],
            'quality' => [
                'average_scc' => $this->getAverageSCC($farmer),
                'rejection_rate' => $this->getRejectionRate($farmer),
                'grade_distribution' => $this->getGradeDistribution($farmer),
            ],
            'top_producers' => $this->getTopProducers($farmer),
            'declining_producers' => $this->getDecliningProducers($farmer),
        ];
    }

    /**
     * Health Statistics
     */
    private function getHealthStats(Farmer $farmer)
    {
        $activeReports = $farmer->healthReports()->active()->count();
        $emergencies = $farmer->healthReports()->emergency()->count();

        return [
            'active_reports' => $activeReports,
            'emergency_cases' => $emergencies,
            'pending_diagnosis' => $farmer->healthReports()
                ->where('status', 'Pending Diagnosis')
                ->count(),
            'under_treatment' => $farmer->healthReports()
                ->where('status', 'Under Treatment')
                ->count(),
            'recovered_this_month' => $farmer->healthReports()
                ->where('status', 'Recovered')
                ->whereMonth('updated_at', now()->month)
                ->count(),
            'vaccination_schedule' => [
                'due_today' => VaccinationSchedule::whereHas('animal', fn($q) =>
                    $q->where('farmer_id', $farmer->id)
                )->today()->count(),
                'due_this_week' => VaccinationSchedule::whereHas('animal', fn($q) =>
                    $q->where('farmer_id', $farmer->id)
                )->upcoming(7)->count(),
                'missed' => VaccinationSchedule::whereHas('animal', fn($q) =>
                    $q->where('farmer_id', $farmer->id)
                )->missed()->count(),
            ],
            'vet_appointments' => [
                'upcoming' => $farmer->upcomingVetAppointments()->count(),
                'today' => $farmer->vetAppointments()
                    ->whereDate('appointment_date', today())
                    ->count(),
            ],
            'mastitis_risk_animals' => $this->getMastitisRiskCount($farmer),
            'recent_cases' => $this->getRecentHealthCases($farmer),
        ];
    }

    /**
     * Breeding Statistics
     */
    private function getBreedingStats(Farmer $farmer)
    {
        $totalBreedings = $farmer->breedingRecords()->count();
        $pregnantCows = $farmer->breedingRecords()->pregnant()->count();

        return [
            'total_breedings' => $totalBreedings,
            'pregnant_cows' => $pregnantCows,
            'due_soon' => $farmer->breedingRecords()->dueSoon(7)->count(),
            'overdue' => $farmer->breedingRecords()->overdue()->count(),
            'pending_confirmation' => $farmer->breedingRecords()->pending()->count(),
            'success_rate' => $this->calculateBreedingSuccessRate($farmer),
            'ai_vs_natural' => [
                'ai' => $farmer->breedingRecords()->ai()->count(),
                'natural' => $farmer->breedingRecords()->natural()->count(),
            ],
            'upcoming_deliveries' => $this->getUpcomingDeliveries($farmer),
            'recent_births' => $this->getRecentBirths($farmer),
            'average_calving_interval' => $this->getAverageCalvingInterval($farmer),
        ];
    }

    /**
     * Alerts and Notifications
     */
    private function getAlerts(Farmer $farmer)
    {
        return [
            'critical' => $this->getCriticalAlerts($farmer),
            'warnings' => $this->getWarningAlerts($farmer),
            'info' => $this->getInfoAlerts($farmer),
            'count' => [
                'critical' => count($this->getCriticalAlerts($farmer)),
                'warnings' => count($this->getWarningAlerts($farmer)),
                'info' => count($this->getInfoAlerts($farmer)),
            ],
        ];
    }

    /**
     * Trends Data for Charts
     */
    private function getTrends(Farmer $farmer)
    {
        return [
            'milk_production' => $this->getMilkProductionTrend($farmer),
            'financial' => $this->getFinancialTrend($farmer),
            'health_incidents' => $this->getHealthIncidentTrend($farmer),
        ];
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    private function calculateProfileCompletion(Farmer $farmer): int
    {
        $fields = [
            'farm_name' => !empty($farmer->farm_name),
            'farm_purpose' => !empty($farmer->farm_purpose),
            'location_id' => !empty($farmer->location_id),
            'total_land_acres' => !empty($farmer->total_land_acres),
            'years_experience' => !empty($farmer->years_experience),
            'has_livestock' => $farmer->livestock()->count() > 0,
            'has_profile_photo' => !empty($farmer->profile_photo),
        ];

        $completed = count(array_filter($fields));
        return round(($completed / count($fields)) * 100);
    }

    private function getPregnantCount(Farmer $farmer): int
    {
        return $farmer->breedingRecords()->pregnant()->count();
    }

    private function getAverageAge(Farmer $farmer): ?float
    {
        $avgMonths = $farmer->livestock()
            ->whereNotNull('date_of_birth')
            ->get()
            ->avg(fn($animal) => $animal->date_of_birth->diffInMonths(now()));

        return $avgMonths ? round($avgMonths, 1) : null;
    }

    private function formatAnimal($animal): ?array
    {
        if (!$animal) return null;

        return [
            'id' => $animal->animal_id,
            'tag_number' => $animal->tag_number,
            'breed' => $animal->breed,
            'sex' => $animal->sex,
        ];
    }

    private function calculateGrowth(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $growth = (($current - $previous) / $previous) * 100;

        return [
            'percentage' => round(abs($growth), 1),
            'direction' => $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'neutral'),
        ];
    }

    private function getIncomeByCategory(Farmer $farmer): array
    {
        return $farmer->income()
            ->thisMonth()
            ->with('category')
            ->get()
            ->groupBy('category.category_name')
            ->map(fn($items) => $items->sum('amount'))
            ->toArray();
    }

    private function getExpensesByCategory(Farmer $farmer): array
    {
        return $farmer->expenses()
            ->thisMonth()
            ->with('category')
            ->get()
            ->groupBy('category.category_name')
            ->map(fn($items) => $items->sum('amount'))
            ->toArray();
    }

    private function getTopExpenses(Farmer $farmer): array
    {
        return $farmer->expenses()
            ->thisMonth()
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn($expense) => [
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'category' => $expense->category?->category_name,
                'date' => $expense->expense_date->format('Y-m-d'),
            ])
            ->toArray();
    }

    private function getAverageSCC(Farmer $farmer): ?float
    {
        $avg = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->where('yield_date', '>=', now()->subDays(30))
            ->avg('somatic_cell_count');

        return $avg ? round($avg) : null;
    }

    private function getRejectionRate(Farmer $farmer): float
    {
        $total = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->thisMonth()
            ->count();

        if ($total == 0) return 0;

        $rejected = MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->thisMonth()
            ->rejected()
            ->count();

        return round(($rejected / $total) * 100, 1);
    }

    private function getGradeDistribution(Farmer $farmer): array
    {
        return MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->thisMonth()
            ->select('quality_grade', DB::raw('count(*) as count'))
            ->groupBy('quality_grade')
            ->get()
            ->pluck('count', 'quality_grade')
            ->toArray();
    }

    private function getTopProducers(Farmer $farmer): array
    {
        return $farmer->livestock()
            ->with(['milkYields' => fn($q) => $q->thisMonth()])
            ->get()
            ->map(function($animal) {
                return [
                    'animal_id' => $animal->animal_id,
                    'tag_number' => $animal->tag_number,
                    'breed' => $animal->breed,
                    'total_milk' => $animal->milkYields->sum('quantity_liters'),
                ];
            })
            ->sortByDesc('total_milk')
            ->take(5)
            ->values()
            ->toArray();
    }

    private function getDecliningProducers(Farmer $farmer): array
    {
        return MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->declining()
            ->with('animal')
            ->get()
            ->map(fn($record) => [
                'animal_id' => $record->animal->animal_id,
                'tag_number' => $record->animal->tag_number,
                'recent_yield' => (float) $record->quantity_liters,
            ])
            ->take(5)
            ->toArray();
    }

    private function getMastitisRiskCount(Farmer $farmer): int
    {
        return MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->where('yield_date', '>=', now()->subDays(7))
            ->mastitisRisk()
            ->distinct('animal_id')
            ->count('animal_id');
    }

    private function getRecentHealthCases(Farmer $farmer): array
    {
        return $farmer->healthReports()
            ->with(['animal', 'diagnoses'])
            ->latest('report_date')
            ->limit(5)
            ->get()
            ->map(fn($report) => [
                'id' => $report->health_id,
                'animal' => $report->animal->tag_number,
                'symptoms' => $report->symptoms,
                'status' => $report->status,
                'priority' => $report->priority,
                'date' => $report->report_date->format('Y-m-d'),
            ])
            ->toArray();
    }

    private function calculateBreedingSuccessRate(Farmer $farmer): float
    {
        $total = $farmer->breedingRecords()->count();
        if ($total == 0) return 0;

        $successful = $farmer->breedingRecords()->whereHas('birthRecord')->count();
        return round(($successful / $total) * 100, 1);
    }

    private function getUpcomingDeliveries(Farmer $farmer): array
    {
        return $farmer->breedingRecords()
            ->pregnant()
            ->with(['dam', 'sire'])
            ->orderBy('expected_delivery_date')
            ->limit(5)
            ->get()
            ->map(fn($record) => [
                'breeding_id' => $record->breeding_id,
                'dam' => $record->dam->tag_number,
                'expected_date' => $record->expected_delivery_date->format('Y-m-d'),
                'days_to_delivery' => $record->days_to_delivery,
                'is_overdue' => $record->is_overdue,
            ])
            ->toArray();
    }

    private function getRecentBirths(Farmer $farmer): array
    {
        return $farmer->birthRecords()
            ->with(['breedingRecord.dam', 'offspringRecords'])
            ->latest('actual_delivery_date')
            ->limit(5)
            ->get()
            ->map(fn($birth) => [
                'birth_id' => $birth->birth_id,
                'dam' => $birth->breedingRecord->dam->tag_number,
                'date' => $birth->actual_delivery_date->format('Y-m-d'),
                'offspring_count' => $birth->offspringRecords->count(),
                'outcome' => $birth->birth_outcome,
            ])
            ->toArray();
    }

    private function getAverageCalvingInterval(Farmer $farmer): ?int
    {
        $intervals = $farmer->breedingRecords()
            ->whereHas('birthRecord')
            ->get()
            ->map(fn($record) => $record->calving_interval)
            ->filter()
            ->avg();

        return $intervals ? round($intervals) : null;
    }

    private function getCriticalAlerts(Farmer $farmer): array
    {
        $alerts = [];

        // Emergency health cases
        $emergencies = $farmer->healthReports()->emergency()->count();
        if ($emergencies > 0) {
            $alerts[] = [
                'type' => 'health',
                'severity' => 'critical',
                'message' => "$emergencies emergency health case(s) require immediate attention",
                'count' => $emergencies,
            ];
        }

        // Overdue deliveries
        $overdue = $farmer->breedingRecords()->overdue()->count();
        if ($overdue > 0) {
            $alerts[] = [
                'type' => 'breeding',
                'severity' => 'critical',
                'message' => "$overdue cow(s) overdue for delivery",
                'count' => $overdue,
            ];
        }

        // Mastitis risk
        $mastitisRisk = $this->getMastitisRiskCount($farmer);
        if ($mastitisRisk > 0) {
            $alerts[] = [
                'type' => 'production',
                'severity' => 'critical',
                'message' => "$mastitisRisk cow(s) at risk of mastitis (high SCC)",
                'count' => $mastitisRisk,
            ];
        }

        return $alerts;
    }

    private function getWarningAlerts(Farmer $farmer): array
    {
        $alerts = [];

        // Low feed stock
        $lowStock = FeedStock::where('farmer_id', $farmer->id)
            ->lowStock()
            ->count();
        if ($lowStock > 0) {
            $alerts[] = [
                'type' => 'feed',
                'severity' => 'warning',
                'message' => "$lowStock feed stock(s) running low",
                'count' => $lowStock,
            ];
        }

        // Missed vaccinations
        $missed = VaccinationSchedule::whereHas('animal', fn($q) =>
            $q->where('farmer_id', $farmer->id)
        )->missed()->count();
        if ($missed > 0) {
            $alerts[] = [
                'type' => 'health',
                'severity' => 'warning',
                'message' => "$missed vaccination(s) missed",
                'count' => $missed,
            ];
        }

        // Declining milk production
        $declining = count($this->getDecliningProducers($farmer));
        if ($declining > 0) {
            $alerts[] = [
                'type' => 'production',
                'severity' => 'warning',
                'message' => "$declining cow(s) showing declining milk production",
                'count' => $declining,
            ];
        }

        return $alerts;
    }

    private function getInfoAlerts(Farmer $farmer): array
    {
        $alerts = [];

        // Due vaccinations
        $dueVaccinations = VaccinationSchedule::whereHas('animal', fn($q) =>
            $q->where('farmer_id', $farmer->id)
        )->upcoming(7)->count();
        if ($dueVaccinations > 0) {
            $alerts[] = [
                'type' => 'health',
                'severity' => 'info',
                'message' => "$dueVaccinations vaccination(s) due this week",
                'count' => $dueVaccinations,
            ];
        }

        // Upcoming deliveries
        $dueSoon = $farmer->breedingRecords()->dueSoon(7)->count();
        if ($dueSoon > 0) {
            $alerts[] = [
                'type' => 'breeding',
                'severity' => 'info',
                'message' => "$dueSoon cow(s) due for delivery this week",
                'count' => $dueSoon,
            ];
        }

        return $alerts;
    }

    private function getMilkProductionTrend(Farmer $farmer): array
    {
        return MilkYieldRecord::whereHas('animal', fn($q) => $q->where('farmer_id', $farmer->id))
            ->where('yield_date', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(yield_date) as date'),
                DB::raw('SUM(quantity_liters) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'value' => (float) $item->total,
            ])
            ->toArray();
    }

    private function getFinancialTrend(Farmer $farmer): array
    {
        $days = 30;
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            $income = $farmer->income()
                ->whereDate('income_date', $date)
                ->sum('amount');

            $expenses = $farmer->expenses()
                ->whereDate('expense_date', $date)
                ->sum('amount');

            $trend[] = [
                'date' => $date,
                'income' => (float) $income,
                'expenses' => (float) $expenses,
                'profit' => (float) ($income - $expenses),
            ];
        }

        return $trend;
    }

    private function getHealthIncidentTrend(Farmer $farmer): array
    {
        return $farmer->healthReports()
            ->where('report_date', '>=', now()->subDays(90))
            ->select(
                DB::raw('DATE_FORMAT(report_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($item) => [
                'month' => $item->month,
                'count' => $item->count,
            ])
            ->toArray();
    }
}
