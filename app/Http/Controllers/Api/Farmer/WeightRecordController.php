<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\WeightRecord;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class WeightRecordController extends Controller
{
    // =================================================================
    // INDEX: List weight records with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex', 'species_id'),
                'animal.species' => fn($q) => $q->select('species_id', 'species_name'),
            ])
            ->latestFirst();

        // Filters
        if ($request->boolean('this_month')) $query->thisMonth();
        if ($request->boolean('underweight')) $query->underweight(200);
        if ($request->boolean('market_ready')) $query->marketReady();
        if ($request->boolean('slow_growth')) $query->slowGrowth(0.6);
        if ($request->boolean('ready_to_sell')) $query->readyToSell();
        if ($request->boolean('needs_weighing')) $query->needsWeighing();
        if ($request->filled('animal_id')) $query->where('animal_id', $request->animal_id);
        if ($request->filled('method')) $query->where('measurement_method', $request->method);

        $records = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $records
        ]);
    }

    // =================================================================
    // SHOW: Single weight record
    // =================================================================
    public function show(Request $request, $weight_id)
    {
        $record = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.species', 'animal.breed'])
            ->findOrFail($weight_id);

        return response()->json([
            'status' => 'success',
            'data' => $record
        ]);
    }

    // =================================================================
    // STORE: Record new weight
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'record_date' => 'required|date|before:tomorrow',
            'weight_kg' => 'required|numeric|min:1|max:2000',
            'body_condition_score' => 'nullable|numeric|min:1|max:5',
            'measurement_method' => 'required|in:Scale,Tape,Visual',
            'heart_girth_cm' => 'nullable|numeric|min:50|max:300',
            'height_cm' => 'nullable|numeric|min:50|max:250',
            'recorded_by' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $animal = Livestock::where('animal_id', $request->animal_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        // Prevent duplicate on same date
        $exists = WeightRecord::where('animal_id', $request->animal_id)
            ->whereDate('record_date', $request->record_date)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Uzito tayari umepimwa tarehe hii'
            ], 400);
        }

        $record = WeightRecord::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Uzito umeandikishwa kikamilifu',
            'data' => $record->load('animal')
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit weight record
    // =================================================================
    public function update(Request $request, $weight_id)
    {
        $record = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($weight_id);

        $validator = Validator::make($request->all(), [
            'weight_kg' => 'sometimes|numeric|min:1|max:2000',
            'body_condition_score' => 'nullable|numeric|min:1|max:5',
            'measurement_method' => 'sometimes|in:Scale,Tape,Visual',
            'heart_girth_cm' => 'nullable|numeric|min:50|max:300',
            'height_cm' => 'nullable|numeric|min:50|max:250',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $record->update($request->only([
            'weight_kg', 'body_condition_score', 'measurement_method',
            'heart_girth_cm', 'height_cm', 'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya uzito imesasishwa',
            'data' => $record
        ]);
    }

    // =================================================================
    // DESTROY: Delete weight record
    // =================================================================
    public function destroy(Request $request, $weight_id)
    {
        $record = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($weight_id);

        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya uzito imefutwa'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $latest = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->whereRaw('record_date = (SELECT MAX(record_date) FROM weight_records w2 WHERE w2.animal_id = weight_records.animal_id)')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_animals' => $latest->count(),
                'avg_weight' => round($latest->avg('weight_kg'), 1),
                'market_ready' => $latest->where('is_market_weight', true)->count(),
                'underweight' => $latest->where('weight_kg', '<', 200)->count(),
                'avg_adg' => round($latest->avg('adg_since_last'), 3),
                'needs_weighing' => WeightRecord::needsWeighing()
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'slow_growers' => WeightRecord::slowGrowth(0.6)
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'total_projected_value' => $latest->sum('estimated_price'),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Critical growth issues
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $slow = WeightRecord::slowGrowth(0.6)
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('record_date')
            ->take(10)
            ->get();

        $needs = WeightRecord::needsWeighing()
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'slow_growth' => $slow,
                'needs_weighing' => $needs,
                'total_alerts' => $slow->count() + $needs->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $farmerId)
                    ->where('status', 'Active')
                    ->select('animal_id as value', 'tag_number', 'name')
                    ->orderBy('tag_number')
                    ->get(),
                'methods' => [
                    ['value' => 'Scale', 'label' => 'Mizani'],
                    ['value' => 'Tape', 'label' => 'Mipira'],
                    ['value' => 'Visual', 'label' => 'Kwa Macho'],
                ],
            ]
        ]);
    }

    // =================================================================
    // PDF: Download weight record
    // =================================================================
    public function downloadPdf(Request $request, $weight_id)
    {
        $record = WeightRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.species', 'animal.breed'])
            ->findOrFail($weight_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.weight-record', compact('record', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $filename = "Ripoti-Uzito-{$record->animal->tag_number}-" .
            Carbon::parse($record->record_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
