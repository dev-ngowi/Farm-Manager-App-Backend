<?php

namespace Database\Factories;

use App\Models\ResearchDataExport;
use App\Models\ResearchDataRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResearchDataExportFactory extends Factory
{
    protected $model = ResearchDataExport::class;

    public function definition(): array
    {
        $request = ResearchDataRequest::approved()->inRandomOrder()->first();
        $format = $this->faker->randomElement(['CSV', 'Excel', 'JSON']);

        return [
            'request_id' => $request?->request_id ?? ResearchDataRequest::factory(),
            'researcher_id' => $request?->researcher_id ?? \App\Models\Researcher::factory(),
            'export_format' => $format,
            'file_name' => "data_export_{$this->faker->uuid}.{$this->extension($format)}",
            'file_size_kb' => $this->faker->numberBetween(500, 50000),
            'file_path' => "exports/{$this->faker->uuid}.{$this->extension($format)}",
            'record_count' => $this->faker->numberBetween(1000, 250000),
            'anonymization_applied' => $this->faker->boolean(95),
            'export_date' => $this->faker->dateTimeBetween('-6 months'),
            'download_count' => $this->faker->numberBetween(0, 5),
            'last_downloaded_at' => $this->faker->optional(0.6)->dateTimeBetween('-3 months'),
            'expires_at' => $this->faker->dateTimeBetween('+1 month', '+2 years'),
        ];
    }

    private function extension($format)
    {
        return match ($format) {
            'CSV' => 'csv',
            'Excel' => 'xlsx',
            'JSON' => 'json',
            'PDF' => 'pdf',
            'SQL' => 'sql',
        };
    }
}
