<?php

// Run: php tests/fhpr-last-ai-date.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Carbon\Carbon::setTestNow('2026-11-17T12:00:00+08:00');

use App\Http\Controllers\ModuleController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.connections.fhpr_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'fhpr_test']);
Schema::create('cooperatives', function (Blueprint $t) { $t->id(); $t->string('cooperative_name'); });
Schema::create('farmers', function (Blueprint $t) { $t->id(); $t->integer('cooperative_id'); });
Schema::create('carabaos', function (Blueprint $t) {
    $t->id(); $t->integer('farmer_id')->nullable(); $t->integer('cooperative_id');
    foreach (['sex', 'classification', 'registry_number', 'animal_id'] as $column) $t->string($column)->nullable();
});
Schema::create('fhpr_records', function (Blueprint $t) {
    $t->id(); $t->integer('carabao_id'); $t->integer('cooperative_id');
    foreach (['registry_number', 'status_updated', 'tag_number', 'source_data'] as $column) $t->string($column)->nullable();
    $t->date('date_of_birth')->nullable(); $t->date('last_ai_date')->nullable();
    $t->date('actual_pd_date')->nullable(); $t->date('expected_calving_date')->nullable();
});
Schema::create('mating_records', function (Blueprint $t) {
    $t->id(); $t->integer('carabao_id'); $t->string('mating_type'); $t->date('mating_date')->nullable();
    $t->date('pregnancy_check_date')->nullable(); $t->string('pregnancy_result')->nullable();
    $t->date('expected_calving_date')->nullable();
    $t->text('source_data')->nullable();
    $t->string('bull_id')->nullable();
});
DB::table('cooperatives')->insert([['id' => 1, 'cooperative_name' => 'A'], ['id' => 2, 'cooperative_name' => 'B']]);
foreach ([1 => 1, 2 => 2, 3 => 1] as $id => $cooperative) {
    DB::table('carabaos')->insert(['id' => $id, 'cooperative_id' => $cooperative, 'sex' => 'F', 'classification' => 'Heifer', 'registry_number' => 'SHARED-ID', 'animal_id' => 'ANIMAL-'.$id]);
    DB::table('fhpr_records')->insert(['id' => $id, 'carabao_id' => $id, 'cooperative_id' => $cooperative, 'last_ai_date' => '2020-01-01']);
}
DB::table('mating_records')->insert([
    ['id' => 1, 'carabao_id' => 1, 'mating_type' => 'AI', 'mating_date' => '2026-05-01'],
    ['id' => 2, 'carabao_id' => 1, 'mating_type' => 'AI', 'mating_date' => '2026-04-15'],
    ['id' => 3, 'carabao_id' => 1, 'mating_type' => 'NM', 'mating_date' => '2026-06-01'],
    ['id' => 4, 'carabao_id' => 2, 'mating_type' => 'AI', 'mating_date' => '2026-07-01'],
    ['id' => 5, 'carabao_id' => 1, 'mating_type' => 'AI', 'mating_date' => null],
]);
DB::table('mating_records')->where('id',1)->update(['bull_id'=>'AI-BULL','source_data'=>json_encode(['mating_api_fields'=>['estrus_type'=>'FTAI']])]);
DB::table('mating_records')->where('id',3)->update(['bull_id'=>'NM-BULL','source_data'=>json_encode(['mating_api_fields'=>['estrus_type'=>'ES']])]);
DB::table('mating_records')->where('id',4)->update(['source_data'=>json_encode(['Type of estrus'=>'NH'])]);
$controller = app(ModuleController::class);
$method = new ReflectionMethod(ModuleController::class, 'fhprRecords');
$read = fn () => $method->invoke($controller)->keyBy('id');
$check = function ($condition, $message) { if (! $condition) throw new RuntimeException($message); };
$original = DB::table('fhpr_records')->get()->toJson();
$rows = $read();
$check($rows->count() === 3 && $rows[1]->last_ai_date === 'May 1,2026', 'Latest AI not selected or NM included');
$check($rows[2]->last_ai_date === 'July 1,2026', 'Separate animals with shared registry ID were mixed');
$check($rows[3]->last_ai_date === '-', 'No AI must not show a stale worksheet date');
$check((int)$rows[1]->ai_count === 2, 'AI count included NM or an undated PD-only record');
$check((int)$rows[2]->ai_count === 1 && (int)$rows[3]->ai_count === 0, 'AI count mixed animals or failed to show zero');
$check($rows[1]->ai_or_n === 'NM' && $rows[1]->estrus_type === null, 'NM must not show estrus');
$check($rows[1]->sire_used === 'NM-BULL' && $rows[3]->sire_used === null, 'Sire must match latest mating or remain blank');
$check($rows[2]->ai_or_n === 'AI' && $rows[2]->estrus_type === 'NH', 'Animal match or legacy estrus mapping failed');
$check($rows[3]->ai_or_n === null && $rows[3]->estrus_type === null, 'Missing mating must stay blank');
DB::table('mating_records')->where('id', 1)->update(['mating_date' => '2026-06-15']);
$check($read()[1]->last_ai_date === 'June 15,2026', 'Edited date not reflected');
$check($read()[1]->ai_or_n === 'AI' && $read()[1]->estrus_type === 'FTAI', 'Mating type and heat must come from the same latest service');
$check($read()[1]->sire_used === 'AI-BULL', 'Sire did not follow latest mating correction');
DB::table('mating_records')->where('id', 1)->update(['mating_date' => '2026-04-01']);
$check($read()[1]->last_ai_date === 'April 15,2026', 'Maximum was not recalculated after correction');
DB::table('mating_records')->where('id', 2)->update(['mating_type' => 'NM']);
$check((int)$read()[1]->ai_count === 1, 'AI count did not refresh after mating type correction');
DB::table('mating_records')->where('id', 1)->update(['pregnancy_check_date' => '2026-07-01', 'pregnancy_result' => '+']);
DB::table('mating_records')->where('id', 2)->update(['pregnancy_check_date' => '2026-08-01', 'pregnancy_result' => '-']);
$check($read()[1]->pregnancy_status === 'Negative', 'Latest PD result not selected, or NM diagnosis excluded');
$check($read()[1]->days_pregnant === null, 'Negative PD must not show pregnancy days');
$check($read()[1]->actual_pd_date === 'August 1,2026' && $read()[1]->expected_calving_date === '-', 'PD Date or negative calving display mismatch');
DB::table('mating_records')->where('id', 5)->update(['pregnancy_check_date' => '2026-09-01', 'pregnancy_result' => 'Positive']);
$check($read()[1]->pregnancy_status === 'Positive', 'Edited or PD-only result not reflected');
$check($read()[1]->days_pregnant === 0, 'Undated positive must match Mating zero days');
$check($read()[1]->actual_pd_date === 'September 1,2026' && $read()[1]->expected_calving_date === '-', 'PD-only dates mismatch');
$check($read()[2]->pregnancy_status === null && $read()[3]->pregnancy_status === null, 'Pregnancy statuses mixed between animal IDs');
DB::table('mating_records')->where('id', 4)->update(['pregnancy_result' => 'Pending']);
$check($read()[2]->pregnancy_status === 'Pending', 'Pending PD result missing');
$check($read()[2]->days_pregnant === null, 'Pending PD must not show pregnancy days');
DB::table('mating_records')->where('id', 5)->update(['mating_date' => '2026-02-01']);
$check($read()[1]->days_pregnant === 289, 'Days must use the same mating record as latest PD, not latest AI date');
$check($read()[1]->expected_calving_date === 'December 8,2026', 'Expected calving must match Mating 310-day fallback');
DB::table('mating_records')->where('id', 5)->update(['expected_calving_date' => '2026-12-10']);
$check($read()[1]->expected_calving_date === 'December 10,2026', 'Saved expected calving must take priority over calculation');
DB::table('mating_records')->where('id', 5)->update(['mating_date' => '2026-06-01']);
$check($read()[1]->days_pregnant === 169, 'Days did not refresh after Mating Date correction');
DB::table('mating_records')->where('id', 5)->update(['mating_date' => '2027-01-01']);
$check($read()[1]->days_pregnant === 0, 'Future mating date produced negative pregnancy days');
$check(DB::table('fhpr_records')->get()->toJson() === $original, 'Saved FHPR data was overwritten');
\Carbon\Carbon::setTestNow();
echo "PASS: Latest AI only, matching animal identity, edited-date refresh, missing dates, formatting, no duplicate rows and unchanged stored FHPR data.\n";
