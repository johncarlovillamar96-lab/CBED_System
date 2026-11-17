<?php

// Run: php tests/fhpr-dry-off-date.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Carbon::setTestNow('2026-11-17T12:00:00+08:00');

use App\Http\Controllers\ModuleController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

config(['database.connections.dry_off_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'dry_off_test']);
Schema::create('carabaos',function(Blueprint $table){
    $table->id(); $table->string('classification')->nullable(); $table->string('status'); $table->string('nature_of_exit')->nullable(); $table->timestamp('updated_at')->nullable();
    $table->integer('cooperative_id')->nullable(); $table->string('registry_number')->nullable();
});
DB::table('carabaos')->insert(['id'=>166,'classification'=>'CowLactating','status'=>'Live']);
Schema::create('mating_records',function(Blueprint $table){
    $table->id();$table->integer('carabao_id');$table->date('mating_date')->nullable();$table->date('pregnancy_check_date')->nullable();$table->string('pregnancy_result')->nullable();
});
Schema::create('fhpr_records', function (Blueprint $table) {
    $table->id(); $table->date('dry_off_date')->nullable(); $table->string('status_updated'); $table->integer('carabao_id'); $table->timestamp('updated_at')->nullable();
    $table->integer('days_in_milk')->nullable(); $table->text('source_data')->nullable();
    $table->integer('cooperative_id')->nullable(); $table->string('registry_number')->nullable(); $table->timestamp('created_at')->nullable();
});
DB::table('fhpr_records')->insert(['id' => 1, 'status_updated' => 'CowLactating', 'carabao_id' => 166, 'days_in_milk'=>474, 'source_data'=>json_encode(['Days in Milk'=>474])]);
$controller = app(ModuleController::class);
$check = function ($pass, $message) { if (! $pass) throw new RuntimeException($message); };
$response = $controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date', 'PATCH', ['dry_off_date' => '2026-05-01', 'status_updated' => 'OTHER', 'carabao_id' => 999]), 1);
$row = DB::table('fhpr_records')->find(1);
$check($row->dry_off_date === '2026-05-01' && $response->getData()->display_date === 'May 1,2026', 'Save or display format failed');
$check($row->status_updated === 'CowDry' && $row->carabao_id === 166, 'Classification or animal identity incorrect');
$check(DB::table('carabaos')->find(166)->classification==='CowDry' && $response->getData()->classification==='CowDry', 'Inventory classification did not update');
$check(json_decode($row->source_data,true)['fhpr_lactation_start_date']==='2025-07-31', 'Original start date was not preserved');
$before = (array) $row;
foreach (['2026-02-30', 'invalid', '05/01/2026', '2025-07-30'] as $invalid) {
    try {
        $controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date', 'PATCH', ['dry_off_date' => $invalid]), 1);
        throw new RuntimeException('Invalid date accepted');
    } catch (ValidationException $e) {}
    $check((array) DB::table('fhpr_records')->find(1) === $before, 'Validation failure changed data');
}
$response=$controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date','PATCH',['dry_off_date'=>'2026-05-31']),1);
$check($response->getData()->days_in_milk===304 && $response->getData()->lactation_start_display==='July 31,2025', 'July 31 to May 31 must be 304 days');
$controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date','PATCH',['dry_off_date'=>'2026-06-01']),1);
$check(DB::table('fhpr_records')->find(1)->days_in_milk===305, 'Repeated edits moved the start date');
$controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date', 'PATCH', ['dry_off_date' => null]), 1);
$check(DB::table('fhpr_records')->find(1)->dry_off_date === null, 'Clearing the date failed');
$check(DB::table('fhpr_records')->find(1)->days_in_milk===474, 'Clearing did not restore days from fixed start to today');
$check(DB::table('carabaos')->find(166)->classification==='CowLactating', 'Clearing dry-off must restore the lactating classification');
foreach([['Dead',null],['Out','Culled'],['Out','Transferred']] as $i=>[$status,$exit]){
    $id=$i+2;$animalId=167+$i;
    DB::table('carabaos')->insert(['id'=>$animalId,'classification'=>null,'status'=>$status,'nature_of_exit'=>$exit]);
    DB::table('fhpr_records')->insert(['id'=>$id,'status_updated'=>'','carabao_id'=>$animalId]);
    $controller->updateFhprDryOffDate(Request::create('/fhpr/'.$id.'/dry-off-date','PATCH',['dry_off_date'=>'2026-05-31']),$id);
    $check(DB::table('carabaos')->find($animalId)->classification===null, 'Exited animal classification was repopulated');
}
$imported=(object)['days_in_milk'=>304,'source_data'=>json_encode(['Dry Off Date'=>\PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(new DateTime('2026-05-31'))])];
$check(\App\Support\FhprLactation::start($imported)->toDateString()==='2025-07-31', 'Imported completed lactation start moved');
try {
    $controller->updateFhprDryOffDate(Request::create('/fhpr/999/dry-off-date', 'PATCH', ['dry_off_date' => '2026-05-01']), 999);
    throw new RuntimeException('Missing record was accepted');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $check($e->getStatusCode() === 404, 'Wrong missing-record response');
}
DB::table('carabaos')->where('id',166)->update(['cooperative_id'=>1,'registry_number'=>'TEST','classification'=>'CowLactating']);
DB::table('fhpr_records')->where('id',1)->update(['cooperative_id'=>1,'registry_number'=>'TEST']);
$importer=app(\App\Http\Controllers\CooperativeWorkbookImportController::class);
$persist=new ReflectionMethod($importer,'persistRecordChunk');
$persist->invoke($importer,[['cooperative_id'=>1,'registry_number'=>'TEST','carabao_id'=>166,'dry_off_date'=>'2026-05-31','status_updated'=>'CowLactating']],'fhpr_records','fhpr_breeding');
$check(DB::table('carabaos')->find(166)->classification==='CowDry' && DB::table('fhpr_records')->find(1)->status_updated==='CowDry', 'Imported Dry Off Date did not force CowDry');
DB::table('mating_records')->insert(['carabao_id'=>166,'mating_date'=>'2026-01-01','pregnancy_check_date'=>'2026-03-01','pregnancy_result'=>'Positive']);
\App\Support\FhprClassification::syncAnimal(166);
$check(DB::table('carabaos')->find(166)->classification==='CowDryPregnant', 'Positive dry cow was not synchronized');
$controller->updateFhprDryOffDate(Request::create('/fhpr/1/dry-off-date','PATCH',['dry_off_date'=>null]),1);
$check(DB::table('carabaos')->find(166)->classification==='CowLactatingPregnant', 'Positive lactating cow was not synchronized');
DB::table('mating_records')->where('carabao_id',166)->update(['pregnancy_result'=>'Negative']);
\App\Support\FhprClassification::syncAnimal(166);
$check(DB::table('carabaos')->find(166)->classification==='CowLactating', 'Negative PD did not remove pregnant classification');
\Illuminate\Support\Carbon::setTestNow();
echo "PASS: Fixed start date, 474 to 304 days after dry-off, repeated edits, clearing, imported dates, validation and unchanged unrelated fields.\n";
