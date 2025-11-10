<?php

namespace App\Exports;

use App\Models\HealthReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HealthReportExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles
{
    protected $farmerId;

    public function __construct($farmerId)
    {
        $this->farmerId = $farmerId;
    }

    public function collection()
    {
        return HealthReport::where('farmer_id', $this->farmerId)
            ->with(['animal', 'diagnoses.vet'])
            ->orderByDesc('report_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Ripoti ID',
            'Namba ya Mifugo',
            'Jina la Mifugo',
            'Dalili',
            'Tarehe ya Dalili',
            'Ukali',
            'Kipaumbele',
            'Hali ya Sasa',
            'Ugonjwa',
            'Daktari',
            'Tarehe ya Ripoti',
            'Maeneo (GPS)',
        ];
    }

    public function map($report): array
    {
        $diagnosis = $report->diagnoses->first();

        return [
            $report->health_id,
            $report->animal?->tag_number ?? 'N/A',
            $report->animal?->name ?? 'Hajapewa Jina',
            substr($report->symptoms, 0, 100) . (strlen($report->symptoms) > 100 ? '...' : ''),
            $report->symptom_onset_date?->format('d/m/Y') ?? 'N/A',
            $report->severity,
            $report->priority,
            $report->status,
            $diagnosis?->disease_condition ?? 'Haijagunduliwa',
            $diagnosis?->vet?->name ?? 'N/A',
            $report->report_date->format('d/m/Y'),
            $report->location_url ? 'Bofya Hapa' : 'Hakuna GPS',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1e40af'],
                ],
            ],
        ];
    }
}
