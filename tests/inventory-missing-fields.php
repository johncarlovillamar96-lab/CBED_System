<?php

// Run: php tests/inventory-missing-fields.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\InventoryMissingFields;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.connections.inventory_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'inventory_test']);
Schema::create('farmers', function (Blueprint $table) {
    $table->id(); $table->integer('cooperative_id');
    foreach (['first_name', 'middle_name', 'last_name'] as $column) $table->string($column)->nullable();
});
Schema::create('carabaos', function (Blueprint $table) {
    $table->id(); $table->integer('cooperative_id'); $table->integer('farmer_id')->nullable();
    $columns = (new ReflectionClass(InventoryMissingFields::class))->getConstant('FIELDS');
    foreach (array_unique(['registry_number', 'status', 'updated_at', ...array_values($columns)]) as $column) $table->string($column)->nullable();
});
$check = function (bool $pass, string $message) { if (! $pass) throw new RuntimeException($message); };
DB::table('farmers')->insert(['id' => 1, 'cooperative_id' => 1, 'last_name' => 'Example, Farmer']);
DB::table('carabaos')->insert([
    ['id' => 1, 'cooperative_id' => 1, 'farmer_id' => 1, 'registry_number' => 'GR1', 'status' => 'Live', 'acquisition_cost' => null, 'remarks' => 'Keep this'],
    ['id' => 2, 'cooperative_id' => 1, 'farmer_id' => 1, 'registry_number' => 'GR2', 'status' => 'Live', 'acquisition_cost' => '0', 'remarks' => null],
]);
$records = [
    ['registry' => 'GR1', 'farmer' => 'Example, Farmer', 'date_of_acquisition' => 41590, 'acquisition_cost' => '315,803.66', 'remarks' => 'Replacement'],
    ['registry' => 'GR2', 'farmer' => 'Example, Farmer', 'acquisition_cost' => '123', 'remarks' => 'Filled'],
    ['registry' => 'NOT-IMPORTED', 'farmer' => 'Example, Farmer', 'acquisition_cost' => '9'],
];
$sync = new InventoryMissingFields;
$before = DB::table('carabaos')->get()->toJson();
$preview = $sync->sync($records, 1);
$check($preview['animals_to_update'] === 2 && $preview['skipped_rows'] === 1, 'Preview matching failed');
$check(DB::table('carabaos')->get()->toJson() === $before, 'Preview wrote data');
$result = $sync->sync($records, 1, true);
try {
    $animal = DB::table('carabaos')->find(1);
    $check($animal->acquisition_date === '2013-11-12' && $animal->acquisition_cost === '315803.66', 'Excel date/cost conversion failed');
    $check($animal->remarks === 'Keep this' && DB::table('carabaos')->find(2)->acquisition_cost === '0', 'Populated value was overwritten');
    $check(DB::table('carabaos')->count() === 2 && DB::table('farmers')->count() === 1, 'Records were inserted');
    $check($sync->sync($records, 1)['animals_to_update'] === 0, 'Repeated run is not idempotent');
    $conflict = $sync->sync([['registry' => 'GR1', 'ownership_status' => 'A'], ['registry' => 'GR1', 'ownership_status' => 'B']], 1);
    $check($conflict['animals_to_update'] === 0 && ! empty($conflict['conflicts']), 'Conflicting source rows were not skipped');
    DB::table('carabaos')->insert(['cooperative_id' => 1, 'farmer_id' => 1, 'registry_number' => 'GR1', 'status' => 'Live']);
    $ambiguous = $sync->sync([['registry' => 'GR1', 'farmer' => 'Example, Farmer', 'ownership_status' => 'A']], 1);
    $check($ambiguous['skipped_rows'] === 1 && $ambiguous['animals_to_update'] === 0, 'Ambiguous record was updated');
    $check($sync->sync($records, 2)['animals_to_update'] === 0, 'Cooperative scope was ignored');
    echo "PASS: preview, missing-only updates, Excel conversion, zero preservation, no inserts, repeat runs, conflicts, ambiguity and cooperative scope.\n";
} finally {
    if ($result['backup']) unlink($result['backup']);
}
