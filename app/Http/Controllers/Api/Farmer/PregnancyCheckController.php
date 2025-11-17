<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\PregnancyCheck;
use App\Models\BreedingRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PregnancyCheckController extends Controller
{
    // =================================================================
    // INDEX: List checks for farmer's breedings
    // =================================================================
    public function index(Request $request)
    {
        $query = PregnancyCheck::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'breeding.dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'breeding.sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'vet' => fn($q) => $q->select('id', 'user_id')->with('user:id,firstname,lastname')
            ])
            ->latest('check_date');

        if ($request->filled('breeding_id')) {
            $query->where('breeding_id', $request->breeding_id);
        }
        if ($request->boolean('pregnant')) $query->pregnant();
        if ($request->boolean('twins')) $query->twinsDetected();
        if ($request->boolean('due_soon')) $query->dueSoon(14);
        if ($request->boolean('overdue')) $query->overdue();

        $checks = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $checks
        ]);
    }

    // =================================================================
    // STORE: Record new pregnancy check
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'breeding_id' => 'required|exists:breeding_records,breeding_id',
            'vet_id' => 'nullable|exists:veterinarians,id',
            'check_date' => 'required|date|before:tomorrow',
            'method' => 'required|in:Ultrasound,Palpation,Blood Test,Visual',
            'result' => 'required|in:Pregnant,Not Pregnant,Unknown',
            'expected_delivery_date' => 'nullable|date|after:check_date',
            'fetus_count' => 'nullable|integer|min:1|max:6',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $breeding = BreedingRecord::where('breeding_id', $request->breeding_id)
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->firstOrFail();

        $check = PregnancyCheck::create($request->all());

        // Update breeding status if confirmed pregnant
        if ($request->result === 'Pregnant') {
            $breeding->update([
                'status' => 'Confirmed Pregnant',
                'expected_delivery_date' => $request->expected_delivery_date
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Ukaguzi wa mimba umeandikishwa',
            'data' => $check->load('breeding.dam', 'vet')
        ], 201);
    }

    // =================================================================
    // SHOW: Single check
    // =================================================================
    public function show(Request $request, $check_id)
    {
        $check = PregnancyCheck::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['breeding.dam', 'breeding.sire', 'vet.user', 'birthRecord'])
            ->findOrFail($check_id);

        return response()->json(['status' => 'success', 'data' => $check]);
    }

    // =================================================================
    // UPDATE: Edit check
    // =================================================================
    public function update(Request $request, $check_id)
    {
        $check = PregnancyCheck::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($check_id);

        $validator = Validator::make($request->all(), [
            'method' => 'sometimes|in:Ultrasound,Palpation,Blood Test,Visual',
            'result' => 'sometimes|in:Pregnant,Not Pregnant,Unknown',
            'expected_delivery_date' => 'nullable|date|after:check_date',
            'fetus_count' => 'nullable|integer|min:1|max:6',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $check->update($request->only([
            'method', 'result', 'expected_delivery_date', 'fetus_count', 'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Ukaguzi wa mimba umesasishwa',
            'data' => $check
        ]);
    }

    // =================================================================
    // DESTROY: Delete check
    // =================================================================
    public function destroy(Request $request, $check_id)
    {
        $check = PregnancyCheck::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($check_id);

        $check->delete();

        return response()->json(['status' => 'success', 'message' => 'Ukaguzi umefutwa']);
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
                'breedings' => BreedingRecord::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->where('status', '!=', 'Failed')
                    ->with(['dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name')])
                    ->get()
                    ->map(fn($b) => [
                        'value' => $b->breeding_id,
                        'label' => "{$b->dam->tag_number} - " . Carbon::parse($b->breeding_date)->format('d/m/Y')
                    ]),
                'methods' => [
                    ['value' => 'Ultrasound', 'label' => 'Ultrasound'],
                    ['value' => 'Palpation', 'label' => 'Kupapasa'],
                    ['value' => 'Blood Test', 'label' => 'Damu'],
                    ['value' => 'Visual', 'label' => 'Kwa Macho'],
                ],
                'results' => [
                    ['value' => 'Pregnant', 'label' => 'Mimba'],
                    ['value' => 'Not Pregnant', 'label' => 'Hapana Mimba'],
                    ['value' => 'Unknown', 'label' => 'Haijulikani'],
                ],
            ]
        ]);
    }

    // =================================================================
    // PDF: Download pregnancy check report
    // =================================================================
    public function downloadPdf(Request $request, $check_id)
    {
        $check = PregnancyCheck::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['breeding.dam', 'breeding.sire', 'vet.user', 'birthRecord'])
            ->findOrFail($check_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.pregnancy-check', compact('check', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Ripoti-Mimba-{$check->breeding->dam->tag_number}-" .
            Carbon::parse($check->check_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
