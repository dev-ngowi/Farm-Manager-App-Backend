<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Ward; 
use App\Models\District;

class WardSeeder extends Seeder
{
    public function run(): void
    {
        $apiUrl = "https://tzgeodata.vercel.app/api/v1/wards/";
        
        // --- FIX: Tell Guzzle/cURL where to find the certificate bundle ---
        $certPath = storage_path('cacert.pem'); // Assumes you saved it in storage/
        
        // 1. Fetch the data using the 'withOptions' to set the 'verify' flag
        $response = Http::withOptions([
            'verify' => $certPath,
        ])->get($apiUrl);
        // ------------------------------------------------------------------

        if ($response->successful()) {
            // ... (The rest of your existing logic for processing and inserting data)
            $wardsData = $response->json();
            
            $districts = District::all()->pluck('id', 'district_name')->mapWithKeys(function ($id, $name) {
                return [strtoupper($name) => $id];
            });

            $wardsToInsert = [];
            $batchSize = 500; 
            $count = 0;

            DB::transaction(function () use ($wardsData, $districts, &$wardsToInsert, $batchSize, &$count) {
                foreach ($wardsData as $ward) {
                    $districtNameFromApi = strtoupper($ward['district_name'] ?? '');
                    $districtId = $districts[$districtNameFromApi] ?? null;

                    if ($districtId) {
                        $wardsToInsert[] = [
                            'district_id' => $districtId,
                            'ward_name' => $ward['ward_name'] ?? 'Unknown Ward',
                            'ward_code' => $ward['ward_code'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                    }

                    if (count($wardsToInsert) >= $batchSize) {
                        DB::table('wards')->insert($wardsToInsert);
                        $wardsToInsert = [];
                    }
                }

                if (!empty($wardsToInsert)) {
                    DB::table('wards')->insert($wardsToInsert);
                }
            });

            $this->command->info("✅ Successfully seeded {$count} wards from the API!");
        } else {
            $this->command->error("❌ Failed to fetch wards data from the API: " . $response->status());
        }
    }
}