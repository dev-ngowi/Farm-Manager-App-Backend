<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TanzaniaRegionsAndDistrictsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        DB::transaction(function () {
            $regions = [
                'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Katavi', 'Kigoma',
                'Kilimanjaro', 'Lindi', 'Manyara', 'Mara', 'Mbeya', 'Morogoro', 'Mtwara', 'Mwanza',
                'Njombe', 'Pemba Kaskazini', 'Pemba Kusini', 'Pwani', 'Rukwa', 'Ruvuma',
                'Shinyanga', 'Simiyu', 'Singida', 'Songwe', 'Tabora', 'Tanga',
                'Unguja Kaskazini', 'Unguja Mjini Magharibi', 'Unguja Kusini'
            ];

            $regionMap = [];

            // Insert Regions
            foreach ($regions as $index => $region) {
                $code = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                $regionId = DB::table('regions')->insertGetId([
                    'region_name' => $region,
                    'region_code' => "TZ-{$code}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $regionMap[$region] = $regionId;
            }

            // Districts grouped by region (official & cleaned)
            $districts = [
                'Arusha' => ['Arusha City', 'Arusha District', 'Karatu District', 'Longido District', 'Meru District', 'Monduli District', 'Ngorongoro District'],
                'Dar es Salaam' => ['Ilala Municipal', 'Kinondoni Municipal', 'Temeke Municipal', 'Kigamboni Municipal', 'Ubungo Municipal'],
                'Dodoma' => ['Bahi District', 'Chamwino District', 'Chemba District', 'Dodoma Municipal', 'Kondoa District', 'Kongwa District', 'Mpwapwa District'],
                'Geita' => ['Bukombe District', 'Chato District', 'Geita Town', 'Mbogwe District', 'Nyang\'hwale District'],
                'Iringa' => ['Iringa District', 'Iringa Municipal', 'Kilolo District', 'Mafinga Town', 'Mufindi District'],
                'Kagera' => ['Biharamulo District', 'Bukoba District', 'Bukoba Municipal', 'Karagwe District', 'Kyerwa District', 'Missenyi District', 'Muleba District', 'Ngara District'],
                'Katavi' => ['Mlele District', 'Mpanda District', 'Mpanda Town'],
                'Kigoma' => ['Buhigwe District', 'Kakonko District', 'Kasulu District', 'Kasulu Town', 'Kibondo District', 'Kigoma District', 'Kigoma-Ujiji Municipal', 'Uvinza District'],
                'Kilimanjaro' => ['Hai District', 'Moshi District', 'Moshi Municipal', 'Mwanga District', 'Rombo District', 'Same District', 'Siha District'],
                'Lindi' => ['Kilwa District', 'Lindi District', 'Lindi Municipal', 'Liwale District', 'Nachingwea District', 'Ruangwa District'],
                'Manyara' => ['Babati District', 'Babati Town', 'Hanang District', 'Kiteto District', 'Mbulu District', 'Simanjiro District'],
                'Mara' => ['Bunda District', 'Butiama District', 'Musoma District', 'Musoma Municipal', 'Rorya District', 'Serengeti District', 'Tarime District'],
                'Mbeya' => ['Busokelo District', 'Chunya District', 'Kyela District', 'Mbarali City', 'Mbeya District', 'Rungwe District'],
                'Morogoro' => ['Gairo District', 'Ifakara Township', 'Kilombero District', 'Kilosa District', 'Malinyi District', 'Morogoro District', 'Morogoro Municipal', 'Mvomero District', 'Ulanga District'],
                'Mtwara' => ['Masasi District', 'Masasi Town', 'Mtwara District', 'Mtwara Municipal', 'Nanyumbu District', 'Newala District', 'Tandahimba District'],
                'Mwanza' => ['Ilemela Municipal', 'Kwimba District', 'Magu District', 'Misungwi District', 'Nyamagana Municipal', 'Sengerema District', 'Ukerewe District'],
                'Njombe' => ['Ludewa District', 'Makambako Town', 'Makete District', 'Njombe District', 'Njombe Town', 'Wanging\'ombe District'],
                'Pemba Kaskazini' => ['Micheweni District', 'Wete District', 'Kaskazini A District', 'Kaskazini B District'],
                'Pemba Kusini' => ['Chake Chake District', 'Mkoani District'],
                'Pwani' => ['Bagamoyo District', 'Kibaha District', 'Kibaha Town', 'Kisarawe District', 'Mafia District', 'Mkuranga District', 'Rufiji District'],
                'Rukwa' => ['Kalambo District', 'Nkasi District', 'Sumbawanga District', 'Sumbawanga Municipal'],
                'Ruvuma' => ['Mbinga District', 'Namtumbo District', 'Nyasa District', 'Songea District', 'Songea Municipal', 'Tunduru District'],
                'Shinyanga' => ['Kahama Town', 'Kahama District', 'Kishapu District', 'Shinyanga District', 'Shinyanga Municipal'],
                'Simiyu' => ['Bariadi District', 'Busega District', 'Itilima District', 'Maswa District', 'Meatu District'],
                'Singida' => ['Ikungi District', 'Iramba District', 'Manyoni District', 'Mkalama District', 'Singida District', 'Singida Municipal'],
                'Songwe' => ['Ileje District', 'Mbozi District', 'Momba District', 'Songwe District'],
                'Tabora' => ['Igunga District', 'Kaliua District', 'Nzega District', 'Sikonge District', 'Tabora Municipal', 'Urambo District', 'Uyui District'],
                'Tanga' => ['Handeni District', 'Handeni Town', 'Kilindi District', 'Korogwe District', 'Korogwe Town', 'Lushoto District', 'Mkinga District', 'Muheza District', 'Pangani District', 'Tanga City'],
                'Unguja Kaskazini' => ['Kaskazini A District', 'Kaskazini B District'],
                'Unguja Mjini Magharibi' => ['Mjini District', 'Magharibi District'],
                'Unguja Kusini' => ['Kati District', 'Kusini District'],
            ];

            $usedDistrictNames = [];

            foreach ($districts as $regionName => $districtList) {
                $regionId = $regionMap[$regionName] ?? null;
                if (!$regionId) continue;

                foreach ($districtList as $index => $districtName) {
                    // Avoid duplicates (e.g., "Mbeya District" appears twice in raw data)
                    if (in_array($districtName, $usedDistrictNames)) {
                        continue;
                    }
                    $usedDistrictNames[] = $districtName;

                    // Generate district code: TZ-XXYY (Region index + district index)
                    $regionCode = str_pad(array_search($regionName, $regions) + 1, 2, '0', STR_PAD_LEFT);
                    $districtCode = "TZ{$regionCode}" . str_pad($index + 1, 2, '0', STR_PAD_LEFT);

                    DB::table('districts')->insert([
                        'region_id' => $regionId,
                        'district_name' => $districtName,
                        'district_code' => $districtCode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->command->info('Tanzania regions and districts seeded successfully!');
    }
}
