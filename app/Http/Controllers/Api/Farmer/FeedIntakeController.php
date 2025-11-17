<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\FeedIntakeRecord;
use App\Models\Livestock;
use App\Models\FeedInventory;
use App\Models\FeedStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class FeedIntakeController extends Controller
{
    // =================================================================
    // INDEX: List all feed intake records with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex'),
                'feed' => fn($q) => $q->select('feed_id', 'name', 'feed_type', 'protein_percentage'),
                'stock' => fn($q) => $q->select('stock_id', 'batch_number', 'purchase_price', 'quantity_purchased_kg'),
            ])
            ->withCount(['milkYield as milk_today_liters' => fn($q) => $q->whereDate('yield_date', today())])
            ->orderByDesc('intake_date')
            ->orderBy('feeding_time');

        // Filters
        if ($request->boolean('today')) $query->today();
        if ($request->boolean('this_week')) $query->thisWeek();
        if ($request->boolean('this_month')) $query->thisMonth();
        if ($request->filled('animal_id')) $query->forAnimal($request->animal_id);
        if ($request->filled('feed_id')) $query->byFeed($request->feed_id);
        if ($request->filled('feed_type')) $query->byFeedType($request->feed_type);
        if ($request->boolean('morning')) $query->morning();
        if ($request->boolean('evening')) $query->evening();
        if ($request->boolean('high_cost')) $query->highCost(300); // > 300 TZS/day
        if ($request->boolean('low_efficiency')) $query->lowEfficiency(1.4);
        if ($request->boolean('profitable')) $query->profitable();

        $records = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $records
        ]);
    }

    // =================================================================
    // SHOW: Single feed intake record
    // =================================================================
    public function show(Request $request, $intake_id)
    {
        $record = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal.breed',
                'animal.species',
                'feed',
                'stock',
                'milkYield'
            ])
            ->findOrFail($intake_id);

        return response()->json([
            'status' => 'success',
            'data' => $record
        ]);
    }

    // =================================================================
    // STORE: Record new feed intake + deduct from stock
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'feed_id' => 'required|exists:feed_inventory,feed_id',
            'stock_id' => 'required|exists:feed_stock,stock_id',
            'intake_date' => 'required|date|before:tomorrow',
            'feeding_time' => 'required|in:Morning,Evening,Midday',
            'quantity' => 'required|numeric|min:0.1|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Security: Animal must belong to farmer
        $animal = Livestock::where('animal_id', $request->animal_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        // Check stock belongs to farmer and has enough quantity
        $stock = FeedStock::where('stock_id', $request->stock_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->where('feed_id', $request->feed_id)
            ->where('remaining_kg', '>=', $request->quantity)
            ->firstOrFail();

        DB::transaction(function () use ($request, $animal, $stock) {
            // 1. Create Feed Intake Record
            $intake = FeedIntakeRecord::create([
                'animal_id' => $request->animal_id,
                'feed_id' => $request->feed_id,
                'stock_id' => $request->stock_id,
                'intake_date' => $request->intake_date,
                'feeding_time' => $request->feeding_time,
                'quantity' => $request->quantity,
                'cost_per_unit_used' => $stock->cost_per_kg,
                'notes' => $request->notes,
            ]);

            // 2. Deduct from stock
            $stock->decrement('remaining_kg', $request->quantity);

            // Optional: Log usage history
            // FeedStockHistory::create([...]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Malisho limeandikishwa kikamilifu',
            'data' => FeedIntakeRecord::with(['animal', 'feed'])->find($intake->intake_id ?? DB::getPdo()->lastInsertId())
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit feed intake
    // =================================================================
    public function update(Request $request, $intake_id)
    {
        $intake = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($intake_id);

        $validator = Validator::make($request->all(), [
            'feeding_time' => 'sometimes|in:Morning,Evening,Midday',
            'quantity' => 'sometimes|numeric|min:0.1|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $intake->update($request->only(['feeding_time', 'quantity', 'notes']));

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya malisho imesasishwa',
            'data' => $intake->load(['animal', 'feed'])
        ]);
    }

    // =================================================================
    // DESTROY: Delete feed intake (restore stock)
    // =================================================================
    public function destroy(Request $request, $intake_id)
    {
        $intake = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($intake_id);

        DB::transaction(function () use ($intake) {
            // Restore stock
            if ($intake->stock) {
                $intake->stock->increment('remaining_kg', $intake->quantity);
            }
            $intake->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya malisho imefutwa'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $today = today();
        $thisMonth = now()->format('Y-m');

        $records = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->thisMonth()
            ->with('milkYield')
            ->get();

        $totalCost = $records->sum('cost');
        $totalFeed = $records->sum('quantity');
        $totalMilk = $records->sum('milk_produced');
        $avgFCR = $totalMilk > 0 ? round($totalFeed / $totalMilk, 3) : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_feed_kg' => round($totalFeed, 2),
                'total_cost_tzs' => round($totalCost, 0),
                'avg_cost_per_kg' => $totalFeed > 0 ? round($totalCost / $totalFeed, 0) : 0,
                'total_milk_liters' => round($totalMilk, 2),
                'avg_fcr' => $avgFCR,
                'cost_per_liter' => $totalMilk > 0 ? round($totalCost / $totalMilk, 0) : null,
                'excellent_cows' => $records->where('efficiency_grade', 'Excellent')->count(),
                'poor_performers' => $records->where('efficiency_grade', 'Poor')->count(),
                'morning_better' => FeedIntakeRecord::morningBetter()
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'today_feed_cost' => FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->whereDate('intake_date', $today)
                    ->sum(DB::raw('quantity * cost_per_unit_used')),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: High cost, low efficiency, stock warnings
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $highCost = FeedIntakeRecord::highCost(500)
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('intake_date')
            ->take(10)
            ->get();

        $lowEfficiency = FeedIntakeRecord::lowEfficiency(1.6)
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('intake_date')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'high_feed_cost' => $highCost,
                'low_efficiency_cows' => $lowEfficiency,
                'total_alerts' => $highCost->count() + $lowEfficiency->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For feed intake form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $farmerId)
                    ->whereIn('status', ['Active', 'Lactating'])
                    ->select('animal_id as value', 'tag_number', 'name')
                    ->orderBy('tag_number')
                    ->get(),
                'feeds' => FeedInventory::where('farmer_id', $farmerId)
                    ->select('feed_id as value', 'name as label', 'feed_type')
                    ->orderBy('name')
                    ->get(),
                'stocks' => FeedStock::where('farmer_id', $farmerId)
                    ->where('remaining_kg', '>', 0)
                    ->with('feed:id,name')
                    ->get()
                    ->map(function ($stock) {
                        return [
                            'value' => $stock->stock_id,
                            'label' => "{$stock->feed->name} - Batch {$stock->batch_number} ({$stock->remaining_kg}kg left)",
                            'feed_id' => $stock->feed_id,
                            'cost_per_kg' => $stock->cost_per_kg,
                        ];
                    }),
                'feeding_times' => [
                    ['value' => 'Morning', 'label' => 'Asubuhi'],
                    ['value' => 'Midday', 'label' => 'Mchana'],
                    ['value' => 'Evening', 'label' => 'Jioni'],
                ],
            ]
        ]);
    }

    // =================================================================
    // PDF: Download feed intake report
    // =================================================================
    public function downloadPdf(Request $request, $intake_id)
    {
        $intake = FeedIntakeRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal.breed',
                'animal.species',
                'feed',
                'stock',
                'milkYield'
            ])
            ->findOrFail($intake_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.feed-intake', compact('intake', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $filename = "Ripoti-Malisho-{$intake->animal->tag_number}-" .
            Carbon::parse($intake->intake_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
