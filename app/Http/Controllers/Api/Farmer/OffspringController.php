<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\OffspringRecord;
use App\Models\Livestock;
use App\Models\BirthRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class OffspringController extends Controller
{
    // =================================================================
    // INDEX: List offspring with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'birth.breeding.dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'birth.breeding.sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'livestock' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex', 'current_weight_kg'),
            ])
            ->withCount(['income as total_income', 'expenses as total_expenses'])
            ->orderByDesc('birth.birth_date');

        // Filters
        if ($request->boolean('unregistered')) $query->unregistered();
        if ($request->boolean('healthy')) $query->healthy();
        if ($request->boolean('weak')) $query->weak();
        if ($request->boolean('deceased')) $query->deceased();
        if ($request->boolean('needs_colostrum')) $query->needsColostrum();
        if ($request->boolean('critical')) $query->critical();
        if ($request->filled('gender')) $query->{strtolower($request->gender)}();
        if ($request->boolean('twins')) $query->twins();
        if ($request->boolean('high_value')) $query->highValue();
        if ($request->boolean('ready_for_weaning')) $query->readyForWeaning();
        if ($request->boolean('profitable')) $query->profitableCalves();

        $offspring = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $offspring
        ]);
    }

    // =================================================================
    // SHOW: Single offspring
    // =================================================================
    public function show(Request $request, $offspring_id)
    {
        $offspring = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'birth.breeding.dam.breed',
                'birth.breeding.sire',
                'livestock',
                'weightRecords' => fn($q) => $q->orderByDesc('record_date'),
                'income',
                'expenses'
            ])
            ->findOrFail($offspring_id);

        return response()->json([
            'status' => 'success',
            'data' => $offspring
        ]);
    }

    // =================================================================
    // STORE: Register offspring as livestock (after birth)
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offspring_id' => 'required|exists:offspring_records,offspring_id',
            'tag_number' => 'required|string|max:50|unique:livestock,tag_number',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $offspring = OffspringRecord::where('offspring_id', $request->offspring_id)
            ->whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->firstOrFail();

        if ($offspring->is_registered) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ndama tayari amesajiliwa kama mifugo'
            ], 400);
        }

        DB::transaction(function () use ($request, $offspring) {
            $livestock = Livestock::create([
                'farmer_id' => $offspring->farmer()->first()->farmer_id,
                'species_id' => $offspring->dam()->first()->species_id,
                'breed_id' => $offspring->dam()->first()->breed_id,
                'tag_number' => $request->tag_number,
                'name' => $request->name,
                'sex' => $offspring->gender,
                'date_of_birth' => $offspring->birth->birth_date,
                'weight_at_birth_kg' => $offspring->weight_at_birth_kg,
                'dam_id' => $offspring->dam()->first()->animal_id,
                'sire_id' => $offspring->sire()->first()->animal_id,
                'status' => 'Active',
                'source' => 'Born on Farm',
            ]);

            $offspring->update([
                'livestock_id' => $livestock->animal_id,
                'registered_as_livestock' => true,
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Ndama amesajiliwa kama mifugo kikamilifu',
            'data' => $offspring->load('livestock')
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit offspring record
    // =================================================================
    public function update(Request $request, $offspring_id)
    {
        $offspring = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($offspring_id);

        $validator = Validator::make($request->all(), [
            'health_status' => 'sometimes|in:Healthy,Weak,Deceased',
            'colostrum_intake' => 'sometimes|in:Adequate,Partial,Insufficient,None',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $offspring->update($request->only(['health_status', 'colostrum_intake', 'notes']));

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya ndama imesasishwa',
            'data' => $offspring->fresh()
        ]);
    }

    // =================================================================
    // DESTROY: Delete offspring (only if not registered)
    // =================================================================
    public function destroy(Request $request, $offspring_id)
    {
        $offspring = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($offspring_id);

        if ($offspring->is_registered) {
            return response()->json([
                'status' => 'error',
                'message' => 'Haiwezi kufutwa: Ndama amesajiliwa kama mifugo'
            ], 400);
        }

        $offspring->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya ndama imefutwa'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $offspring = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_born' => $offspring->count(),
                'registered' => $offspring->where('is_registered', true)->count(),
                'unregistered' => $offspring->where('needs_registration', true)->count(),
                'healthy' => $offspring->where('health_status', 'Healthy')->count(),
                'weak' => $offspring->where('health_status', 'Weak')->count(),
                'deceased' => $offspring->where('health_status', 'Deceased')->count(),
                'twins' => $offspring->where('is_twin', true)->count(),
                'colostrum_risk' => $offspring->whereIn('colostrum_status', ['Risk', 'High Risk', 'Critical'])->count(),
                'avg_adg' => $offspring->avg('adg_since_birth'),
                'total_market_value' => $offspring->sum('market_value_estimate'),
                'profitable_calves' => $offspring->where('net_profit', '>', 0)->count(),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Critical offspring
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $critical = OffspringRecord::critical()
            ->whereHas('farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with(['birth.breeding.dam'])
            ->latest('birth.birth_date')
            ->take(10)
            ->get();

        $unregistered = OffspringRecord::unregistered()
            ->whereHas('farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->where('health_status', '!=', 'Deceased')
            ->with(['birth.breeding.dam'])
            ->latest('birth.birth_date')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'critical_health' => $critical,
                'needs_registration' => $unregistered,
                'total_alerts' => $critical->count() + $unregistered->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For registration form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'unregistered_offspring' => OffspringRecord::unregistered()
                    ->whereHas('farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->whereIn('health_status', ['Healthy', 'Weak'])
                    ->with(['birth.breeding.dam'])
                    ->get()
                    ->map(fn($o) => [
                        'value' => $o->offspring_id,
                        'label' => "Ndama #{$o->animal_tag} - Mama: {$o->dam()->first()->tag_number} - {$o->gender}",
                    ]),
                'genders' => [
                    ['value' => 'Male', 'label' => 'Dume'],
                    ['value' => 'Female', 'label' => 'Jike'],
                ],
            ]
        ]);
    }

    // =================================================================
    // PDF: Download offspring report
    // =================================================================
    public function downloadPdf(Request $request, $offspring_id)
    {
        $offspring = OffspringRecord::whereHas('farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'birth.breeding.dam.breed',
                'birth.breeding.sire',
                'livestock',
                'weightRecords' => fn($q) => $q->orderByDesc('record_date')->limit(5),
            ])
            ->findOrFail($offspring_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.offspring', compact('offspring', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $filename = "Ripoti-Ndama-{$offspring->animal_tag}-" .
            Carbon::parse($offspring->birth->birth_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
