<?php

// Run: php tests/mating-worksheet-mapping.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\MatingWorksheetMapping as Mapping;
use App\Http\Controllers\CooperativeWorkbookImportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$check = function ($condition, $message) { if (! $condition) throw new RuntimeException($message); };
config(['database.connections.mating_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'mating_test']);
Schema::create('carabaos', function (Blueprint $table) {
    $table->id(); $table->integer('cooperative_id');
    foreach (['registry_number', 'animal_id', 'tag_number', 'bolus_id'] as $column) $table->string($column)->nullable();
});
Schema::create('mating_records', function (Blueprint $table) {
    $table->id(); $table->integer('cooperative_id')->nullable(); $table->integer('carabao_id')->nullable();
    foreach (['registry_number', 'mating_date', 'mating_type', 'bull_id', 'semen_bull_id', 'technician',
        'return_heat_from', 'return_heat_to', 'pregnancy_check_date', 'pregnancy_result',
        'expected_calving_date', 'actual_calving_date', 'remarks', 'source_data', 'created_at', 'updated_at'] as $column) $table->text($column)->nullable();
});
DB::table('carabaos')->insert([
    ['cooperative_id' => 1, 'registry_number' => 'AI-1'],
    ['cooperative_id' => 1, 'registry_number' => 'NM-1'],
]);
$headers = ['YYYY', 'Breeding date', 'Cow Registry Number', 'Register No.', 'Date of AI', 'Bull ID no.', 'Type of estrus', 'Technician', 'Register No.', 'Bull in date', 'Bull ID no.', 'Expected Date of PD 60 Days', 'Expected Date of PD 90 Days', 'Actual Date of PD', 'Result', 'Mos. Pregnant', 'Confirmed by', 'Remarks', null];
$ai = [2024, 45000, 'WRONG', 'AI-1', 45603, 'AI-BULL', 'ES', 'Technician', null, null, 'WRONG-NM-BULL', null, null, 45664, '+', 2, 'PD Tech', null, 'AI'];
$nm = [2024, 45000, 'WRONG', 'NM-1', 45603, 'WRONG-AI-BULL', 'NH', 'Technician', 'OTHER', 45604, 'NM-BULL', null, null, 45664, '-', null, 'PD Tech', null, 'N'];
$mappedAI = Mapping::fromRow($ai, 'mating');
$mappedNM = Mapping::fromRow($nm, 'mating');
$check($mappedAI['pd_technician'] === 'PD Tech' && $mappedNM['pd_technician'] === 'PD Tech', 'Column Q PD Technician missing');
$check(Mapping::pdTechnicianFromSource(['Confirmed by'=>'WilliamJr. P.']) === 'WilliamJr. P.', 'Existing imported technician not recovered');
$check($mappedAI['pregnancy_months'] === 2 && Mapping::pregnancyMonthsFromSource(['Mos. Pregnant' => 6]) === 6, 'Column P months missing');
$check(Mapping::pregnancyMonthsFromSource(['Mos. Pregnant' => '']) === null, 'Blank months must stay blank');
$check($mappedAI['registry_number'] === 'AI-1' && $mappedAI['mating_date'] === '2024-11-07' && $mappedAI['bull_id'] === 'AI-BULL' && $mappedAI['estrus_type'] === 'ES', 'AI columns incorrect');
$check($mappedNM['registry_number'] === 'NM-1' && $mappedNM['mating_date'] === '2024-11-07' && $mappedNM['mating_type'] === 'NM' && $mappedNM['bull_id'] === 'NM-BULL' && $mappedNM['estrus_type'] === null, 'NM columns incorrect');
$check($mappedAI['pregnancy_check_date'] === '2025-01-07' && $mappedAI['pregnancy_result'] === 'Positive' && $mappedNM['pregnancy_result'] === 'Negative', 'PD columns incorrect');
$fallback = $nm; $fallback[3] = null; $fallback[4] = null;
$check(Mapping::fromRow($fallback, 'mating')['registry_number'] === 'OTHER' && Mapping::fromRow($fallback, 'mating')['mating_date'] === '2024-11-08', 'Natural service fallback incorrect');
$shifted = array_fill(0, 20, null); $shifted[10] = 'NM-1'; $shifted[11] = 45603; $shifted[12] = 'NM-BULL'; $shifted[15] = 45664; $shifted[16] = '-';
$check(Mapping::fromRow($shifted, 'mating_2')['bull_id'] === 'NM-BULL' && Mapping::fromRow($shifted, 'mating_2')['mating_type'] === 'NM', 'Mating (2) layout incorrect');
$check(! Mapping::matchesHeaders(['cooperative_id', 'farmer_id', 'carabao_id', 'breeding_date', 'mating_type'], 'mating'), 'Generic template must retain header mapping');
$book = new Spreadsheet;
$sheet = $book->getActiveSheet();
$sheet->fromArray([$headers, $ai, $nm], null, 'A2');
$import = new ReflectionMethod(CooperativeWorkbookImportController::class, 'importRecords');
$controller = app(CooperativeWorkbookImportController::class);
$import->invoke($controller, $sheet, 'mating_records', 'mating', 1, 0, null, 0);
$check(DB::table('mating_records')->count() === 2, 'First import did not create exactly two services');
$saved = DB::table('mating_records')->where('registry_number', 'AI-1')->first();
$source = json_decode($saved->source_data, true);
$check($source['mating_api_fields']['pd_technician'] === 'PD Tech', 'Imported PD Technician metadata missing');
$check($source['mating_api_fields']['pregnancy_months'] === 2, 'Imported months metadata missing');
$source['mating_api_fields']['straws_used'] = 3;
$source['mating_api_fields']['pregnancy_months'] = 2;
DB::table('mating_records')->where('id', $saved->id)->update(['source_data' => json_encode($source), 'expected_calving_date' => '2025-09-01', 'actual_calving_date' => '2025-09-02']);
$import->invoke($controller, $sheet, 'mating_records', 'mating', 1, 0, null, 0);
$again = DB::table('mating_records')->find($saved->id);
$metadata = json_decode($again->source_data, true)['mating_api_fields'];
$check(DB::table('mating_records')->count() === 2, 'Repeat import created duplicates');
$check($metadata['straws_used'] === 3 && $metadata['pregnancy_months'] === 2 && $metadata['estrus_type'] === 'ES', 'Maintained metadata lost');
$check($again->expected_calving_date === '2025-09-01' && $again->actual_calving_date === '2025-09-02' && $again->technician === 'Technician', 'Maintained fields lost');
echo "PASS: AI/NM columns, date priority, natural fallback, PD mapping, alternate layout, repeat imports and maintained fields.\n";
