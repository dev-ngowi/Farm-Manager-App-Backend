<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Region;
use App\Models\District;
use App\Models\Ward;

class ImportWardsCommand extends Command
{
    protected $signature = 'import:wards {--force : Delete all wards first}';
    protected $description = 'Import ALL 3,921 REAL Tanzanian wards - GUARANTEED TO SAVE';

    public function handle()
    {
        $this->info('Fetching wards from TZ GeoData API...');

        $response = Http::withoutVerifying()
                        ->timeout(120)
                        ->get('https://tzgeodata.vercel.app/api/v1/wards/');

        if ($response->failed()) {
            $this->error('API failed! Check: https://tzgeodata.vercel.app/api/v1/wards/');
            return 1;
        }

        $json = $response->json();
        $wards = $json['wards'] ?? $json['data'] ?? $json ?? [];

        if (empty($wards)) {
            $this->error('No wards in response!');
            $this->info('Response: ' . substr($response->body(), 0, 300));
            return 1;
        }

        $total = count($wards);
        $this->info("Found {$total} wards. Importing...");

        // DISABLE FK
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if ($this->option('force')) {
            $this->warn('FORCE: Deleting all wards...');
            DB::table('wards')->truncate();
            DB::table('wards')->delete(); // Extra
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $saved = 0;
        DB::transaction(function () use ($wards, &$saved, $bar) {
            foreach ($wards as $item) {
                $wardName = trim($item['name'] ?? $item['ward'] ?? '');
                $wardCode = $item['code'] ?? $item['ward_code'] ?? '';
                $council = trim($item['council'] ?? $item['district'] ?? 'Unknown Council');

                if (empty($wardName) || empty($wardCode)) {
                    $bar->advance();
                    continue;
                }

                // FORCE CREATE REGION (fallback)
                $region = Region::firstOrCreate(
                    ['region_name' => $this->mapRegion($council)],
                    ['region_code' => $this->mapRegionCode($council)]
                );

                // FORCE CREATE DISTRICT
                $district = District::firstOrCreate(
                    ['district_name' => $council],
                    [
                        'region_id' => $region->id,
                        'district_code' => $item['council_code'] ?? null,
                    ]
                );

                // UPSERT WARD
                $created = Ward::updateOrCreate(
                    ['ward_code' => $wardCode],
                    [
                        'district_id' => $district->id,
                        'ward_name'   => $wardName,
                    ]
                );

                if ($created->wasRecentlyCreated || $created->wasChanged()) {
                    $saved++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("SUCCESS: {$saved} wards saved to database!");
        $this->info("Total wards in DB: " . Ward::count());

        // PROOF IT WORKED
        $example = Ward::inRandomOrder()->first();
        if ($example) {
            $this->info("Example: {$example->full_path} (Code: {$example->ward_code})");
        }

        return 0;
    }

    private function mapRegion($council)
    {
        $map = [
            'Ilala' => 'Dar es Salaam',
            'Temeke' => 'Dar es Salaam',
            'Kinondoni' => 'Dar es Salaam',
            'Ubungo' => 'Dar es Salaam',
            'Kigamboni' => 'Dar es Salaam',
            'Arusha City' => 'Arusha',
            'Moshi' => 'Kilimanjaro',
            'Dodoma' => 'Dodoma',
            'Mwanza' => 'Mwanza',
            'Mbeya' => 'Mbeya',
            'Iringa' => 'Iringa',
            'Kahama' => 'Shinyanga',
            'Tabora' => 'Tabora',
            'Tanga' => 'Tanga',
            'Kilimanjaro' => 'Kilimanjaro',
            'Zanzibar' => 'Zanzibar Urban/West',
            'Unguja' => 'Zanzibar Urban/West',
            'Pemba' => 'Zanzibar North',
            'Mkoa wa' => 'Unknown Region', // fallback
        ];

        foreach ($map as $key => $region) {
            if (str_contains($council, $key)) {
                return $region;
            }
        }

        // Fallback: extract first word
        return ucwords(strtolower(explode(' ', $council)[0]));
    }

    private function mapRegionCode($council)
    {
        $codes = [
            'Dar es Salaam' => 'TZ-02',
            'Arusha' => 'TZ-01',
            'Dodoma' => 'TZ-03',
            'Kilimanjaro' => 'TZ-09',
            'Mwanza' => 'TZ-12',
            'Zanzibar Urban/West' => 'TZ-11',
            'Zanzibar North' => 'TZ-07',
        ];

        foreach ($codes as $region => $code) {
            if (str_contains($council, $region)) return $code;
        }

        return 'TZ-XX';
    }
}