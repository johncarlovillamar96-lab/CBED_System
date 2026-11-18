<?php

// Run: php tests/overdue-calf-notifications.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\OverdueCalfNotifications;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Carbon::setTestNow('2026-11-17T12:00:00+08:00');
config(['database.connections.alert_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'alert_test']);
Schema::create('cooperatives', function (Blueprint $t) { $t->id(); $t->string('cooperative_name'); });
Schema::create('farmers', function (Blueprint $t) { $t->id(); $t->integer('cooperative_id'); });
Schema::create('carabaos', function (Blueprint $t) {
    $t->id(); $t->integer('cooperative_id')->nullable(); $t->integer('farmer_id')->nullable();
    foreach (['animal_id', 'status', 'nature_of_exit', 'dam_id'] as $field) $t->string($field)->nullable();
    $t->date('birth_date')->nullable();
});
Schema::create('mating_records', function (Blueprint $t) {
    $t->id(); $t->integer('carabao_id'); $t->integer('cooperative_id'); $t->string('registry_number');
    $t->date('mating_date')->nullable(); $t->date('pregnancy_check_date')->nullable(); $t->string('pregnancy_result');
});
Schema::create('calves', function (Blueprint $t) { $t->id(); $t->integer('dam_id'); $t->date('birth_date'); });
DB::table('cooperatives')->insert([['id' => 1, 'cooperative_name' => 'A'], ['id' => 2, 'cooperative_name' => 'B']]);
foreach (range(1, 12) as $id) {
    DB::table('carabaos')->insert(['id' => $id, 'animal_id' => 'ANIMAL-'.$id, 'cooperative_id' => $id === 11 ? 2 : 1, 'status' => $id === 9 ? 'Dead' : ($id === 10 ? 'Out' : 'Live'), 'nature_of_exit' => $id === 10 ? 'Transferred' : null]);
    $days = $id === 2 ? 279 : ($id === 3 ? 280 : ($id === 8 ? 200 : 359));
    DB::table('mating_records')->insert(['id' => $id, 'carabao_id' => $id, 'cooperative_id' => $id === 11 ? 2 : 1,
        'registry_number' => 'ANIMAL-'.$id, 'mating_date' => $id === 7 ? null : Carbon::today('Asia/Singapore')->subDays($days)->toDateString(),
        'pregnancy_check_date' => '2026-08-18', 'pregnancy_result' => $id === 4 ? 'Negative' : 'Positive']);
}
// A repeated older PD is not a second animal, and an old positive cannot override a later negative.
foreach ([1, 4] as $animalId) DB::table('mating_records')->insert(['carabao_id' => $animalId, 'cooperative_id' => 1, 'registry_number' => 'ANIMAL-'.$animalId, 'mating_date' => '2025-11-23', 'pregnancy_check_date' => '2026-02-01', 'pregnancy_result' => 'Positive']);
DB::table('mating_records')->insert(['carabao_id' => 1, 'cooperative_id' => 1, 'registry_number' => 'ANIMAL-1', 'mating_date' => null, 'pregnancy_check_date' => '2026-09-01', 'pregnancy_result' => 'Positive']);
DB::table('carabaos')->insert(['animal_id' => 'CALF-5', 'dam_id' => 'ANIMAL-5', 'birth_date' => '2026-06-01']);
DB::table('carabaos')->insert(['animal_id' => 'OLD-CALF-6', 'dam_id' => 'ANIMAL-6', 'birth_date' => '2025-09-21']);
DB::table('calves')->insert(['dam_id' => 12, 'birth_date' => '2026-06-01']);
$check = function ($condition, $message) { if (! $condition) throw new RuntimeException($message); };
$thresholdRecords = collect([279, 280, 300, 301])->map(fn ($days) => (object) [
    'id' => $days, 'carabao_id' => $days, 'pregnancy_result' => 'Positive',
    'mating_date' => Carbon::today('Asia/Singapore')->subDays($days)->toDateString(),
    'pregnancy_check_date' => null, 'calf_drop_reported' => false,
]);
$thresholds = OverdueCalfNotifications::annotate($thresholdRecords);
$check($thresholds->where('calf_drop_due', true)->pluck('id')->all() === [280, 300, 301], 'Wrong calf entry threshold');
$check($thresholds->where('calf_drop_overdue', true)->pluck('id')->all() === [280, 300, 301], 'Wrong overdue alert threshold');
$before = DB::table('mating_records')->get()->toJson();
$groups = OverdueCalfNotifications::groups();
$check($groups->sum('count') === 4 && $groups->count() === 2, 'Wrong overdue count or cooperative grouping');
$a = $groups->firstWhere('cooperative_id', 1);
$ids = array_column($a['animals'], 'animal_id'); sort($ids);
$check($ids === ['ANIMAL-1', 'ANIMAL-3', 'ANIMAL-6'], 'Threshold, latest result, birth matching or duplicate handling failed');
DB::table('carabaos')->insert(['animal_id' => 'NEW-CALF-1', 'dam_id' => 'ANIMAL-1', 'birth_date' => '2026-10-01']);
$check(OverdueCalfNotifications::groups()->sum('count') === 3, 'Reporting the calf did not resolve the alert');
$check(DB::table('mating_records')->get()->toJson() === $before, 'Notifications changed mating history');
Carbon::setTestNow();
echo "PASS: Cooperative grouping, latest PD, unique animals, 280+ threshold, reported calves, earlier calves, exited animals and automatic alert resolution.\n";
