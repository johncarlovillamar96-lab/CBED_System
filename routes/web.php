<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnimalExitController;
use App\Http\Controllers\AthenaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MtdController;
use App\Http\Controllers\CooperativeImportController;
use App\Http\Controllers\CooperativeWorkbookImportController;
use App\Http\Controllers\CooperativeSpreadsheetController;
use App\Http\Controllers\FarmerSpreadsheetController;
use App\Http\Controllers\MonitoringReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'workspace:admin'])->group(function () {
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup.store');
    Route::put('/signup/{account}', [AuthController::class, 'updateAccount'])->whereNumber('account')->name('signup.update');
    Route::delete('/signup/{account}', [AuthController::class, 'deleteAccount'])->whereNumber('account')->name('signup.destroy');
    Route::post('/athena/chat', [AthenaController::class, 'chat'])->middleware('throttle:10,1')->name('athena.chat');
    Route::post('/athena/records', [AthenaController::class, 'records'])->middleware('throttle:10,1')->name('athena.records');
});

Route::middleware(['auth', 'workspace'])->get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware(['auth', 'workspace'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.page');
Route::middleware(['auth', 'workspace:admin'])->get('/imports/cooperative', [CooperativeImportController::class, 'create'])->name('imports.cooperative.create');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative/preview', [CooperativeImportController::class, 'preview'])->name('imports.cooperative.preview');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative/commit', [CooperativeImportController::class, 'import'])->name('imports.cooperative.import');
Route::middleware(['auth', 'workspace:admin'])->get('/imports/cooperative-workbook', [CooperativeWorkbookImportController::class, 'create'])->name('imports.cooperative-workbook.create');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook', [CooperativeWorkbookImportController::class, 'import'])->name('imports.cooperative-workbook.import');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/batches', [CooperativeWorkbookImportController::class, 'createBatch'])->name('imports.cooperative-workbook.batches.create');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/batches/{batchId}/files', [CooperativeWorkbookImportController::class, 'stageBatchFile'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.files.stage');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/batches/{batchId}/confirm', [CooperativeWorkbookImportController::class, 'confirmBatch'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.confirm');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/unmapped/{identifierId}/map', [CooperativeWorkbookImportController::class, 'mapUnmappedIdentifier'])
    ->whereNumber('identifierId')->name('imports.cooperative-workbook.unmapped.map');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}/import', [CooperativeWorkbookImportController::class, 'importBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.import');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}/retry', [CooperativeWorkbookImportController::class, 'retryBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.retry');
Route::middleware(['auth', 'workspace'])->delete('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}', [CooperativeWorkbookImportController::class, 'removeBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.remove');
Route::middleware(['auth', 'workspace'])->delete('/imports/cooperative-workbook/batches/{batchId}', [CooperativeWorkbookImportController::class, 'cancelBatch'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.cancel');
Route::middleware(['auth', 'workspace'])->post('/imports/cooperative-workbook/{cooperativeId}/{sheetKey}', [CooperativeWorkbookImportController::class, 'importSheet'])
    ->whereNumber('cooperativeId')
    ->name('imports.cooperative-workbook.import-sheet');
Route::middleware(['auth', 'workspace'])->delete('/imports/cooperative-workbook/history', [CooperativeWorkbookImportController::class, 'clearHistory'])
    ->name('imports.cooperative-workbook.clear-history');
Route::middleware(['auth', 'workspace'])->get('/admin/cooperatives/export', [CooperativeSpreadsheetController::class, 'export'])->name('admin.cooperatives.export');
Route::middleware(['auth', 'workspace:admin'])->get('/admin/cooperatives/template', [CooperativeSpreadsheetController::class, 'template'])->name('admin.cooperatives.template');
Route::middleware(['auth', 'workspace'])->post('/admin/cooperatives/import', [CooperativeSpreadsheetController::class, 'import'])->name('admin.cooperatives.import');

// Original CBED_NEW Blade screens
Route::view('/farmers', 'farmer')->middleware(['auth', 'workspace'])->name('ui.farmers');
Route::view('/cooperatives', 'cooperative')->middleware(['auth', 'workspace'])->name('ui.cooperatives');
Route::view('/carabaos', 'carabao')->middleware(['auth', 'workspace'])->name('ui.carabaos');
Route::get('/mating', [MonitoringReportController::class, 'matingPage'])->middleware(['auth', 'workspace'])->name('ui.mating');
Route::view('/calves', 'calf-drop')->middleware(['auth', 'workspace'])->name('ui.calves');
Route::redirect('/calf-drop', '/calves')->middleware(['auth', 'workspace'])->name('ui.calf-drop');
Route::redirect('/health-nutrition', '/health')->middleware(['auth', 'workspace'])->name('ui.health-nutrition');
Route::redirect('/milk-production', '/milk')->middleware(['auth', 'workspace'])->name('ui.milk-production');
Route::redirect('/mil-test', '/mtd')->middleware(['auth', 'workspace'])->name('ui.mil-test');
Route::middleware(['auth', 'workspace'])->get('/nature-of-exit', [AnimalExitController::class, 'index'])->name('ui.nature-of-exit');
Route::view('/mapping', 'mapping')->middleware(['auth', 'workspace'])->name('ui.mapping');
Route::view('/carabao-inventory', 'carabao-inventory')->middleware(['auth', 'workspace'])->name('admin.carabao-inventory');
Route::permanentRedirect('/admin/carabao-inventory', '/carabao-inventory')->middleware(['auth', 'workspace']);
Route::view('/admin/semen-bulls', 'semen-bull')->middleware(['auth', 'workspace'])->name('admin.semen-bulls');
Route::middleware(['auth', 'workspace'])->get('/fhpr', [ModuleController::class, 'index'])->defaults('module', 'fhpr')->name('ui.fhpr');
Route::middleware(['auth', 'workspace'])->patch('/fhpr/{id}/dry-off-date', [ModuleController::class, 'updateFhprDryOffDate'])->whereNumber('id')->name('fhpr.dry-off.update');
Route::middleware(['auth', 'workspace'])->get('/bcs', [ModuleController::class, 'index'])->defaults('module', 'bcs')->name('ui.bcs');
Route::middleware(['auth', 'workspace'])->get('/milk', [ModuleController::class, 'index'])->defaults('module', 'milk')->name('ui.milk');
Route::middleware(['auth', 'workspace'])->post('/milk', [ModuleController::class, 'storeMilkProduction'])->name('milk.production.store');
Route::middleware(['auth', 'workspace'])->get('/mtd', [MtdController::class, 'index'])->name('ui.mtd');
Route::middleware(['auth', 'workspace'])->post('/mtd', [MtdController::class, 'store'])->name('mtd.store');
Route::middleware(['auth', 'workspace'])->put('/mtd/{record}', [MtdController::class, 'update'])->whereNumber('record')->name('mtd.update');
Route::middleware(['auth', 'workspace'])->delete('/mtd/{record}', [MtdController::class, 'destroy'])->whereNumber('record')->name('mtd.delete');
Route::middleware(['auth', 'workspace'])->get('/health', [ModuleController::class, 'index'])->defaults('module', 'health')->name('ui.health');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring', [ModuleController::class, 'monitoring'])->name('ui.monitoring');
Route::middleware(['auth', 'workspace:monitoring'])->get('/activity-logs', [ActivityLogController::class, 'index'])->name('ui.activity-logs');
Route::middleware(['auth', 'workspace:monitoring'])->get('/activity-logs/monitoring', [MonitoringReportController::class, 'activityLogs'])->name('ui.activity-logs.monitoring');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring/cooperatives/{cooperative}/animals', [MonitoringReportController::class, 'animals'])->whereNumber('cooperative')->name('ui.monitoring.animals');
Route::middleware(['auth', 'workspace:monitoring-entry'])->get('/monitoring/cooperatives/{cooperative}/animals/{animal}/mating-records', [MonitoringReportController::class, 'animalMatings'])->whereNumber(['cooperative', 'animal'])->name('ui.monitoring.animal-matings');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring/bcs-scores', [MonitoringReportController::class, 'bcsScores'])->name('ui.monitoring.bcs-scores');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring/semen-bulls', fn () => DB::table('semen_bulls')->whereRaw('LOWER(status) = ?', ['active'])->orderBy('bull_name')->get(['id','bull_id','bull_name','breed','status']))->name('ui.monitoring.semen-bulls');
Route::middleware(['auth', 'workspace:monitoring'])->post('/monitoring/reports', [MonitoringReportController::class, 'store'])->name('ui.monitoring.reports.store');
Route::middleware(['auth', 'workspace:monitoring-entry'])->get('/monitoring/reports/{report}/data', [MonitoringReportController::class, 'editData'])->whereNumber('report')->name('ui.monitoring.reports.data');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring/reports/{report}/export', [MonitoringReportController::class, 'export'])->whereNumber('report')->name('ui.monitoring.reports.export');
Route::middleware(['auth', 'workspace:monitoring'])->get('/monitoring/reports/{report}', [MonitoringReportController::class, 'show'])->whereNumber('report')->name('ui.monitoring.reports.show');
Route::middleware(['auth', 'workspace:monitoring'])->put('/monitoring/reports/{report}', [MonitoringReportController::class, 'update'])->whereNumber('report')->name('ui.monitoring.reports.update');
Route::middleware(['auth', 'workspace'])->get('/exits', [AnimalExitController::class, 'index'])->name('ui.exits');
Route::middleware(['auth', 'workspace'])->post('/exits', [AnimalExitController::class, 'store'])->name('exits.store');
Route::middleware(['auth', 'workspace'])->put('/exits/{record}', [AnimalExitController::class, 'update'])->whereNumber('record')->name('exits.update');
Route::middleware(['auth', 'workspace'])->delete('/exits/{record}', [AnimalExitController::class, 'destroy'])->whereNumber('record')->name('exits.delete');
Route::middleware(['auth', 'workspace'])->get('/meat', [ModuleController::class, 'index'])->defaults('module', 'meat')->name('ui.meat');
Route::middleware(['auth', 'workspace'])->post('/meat', [ModuleController::class, 'storeProductProduction'])->defaults('module', 'meat')->name('meat.production.store');
Route::middleware(['auth', 'workspace'])->get('/manure', [ModuleController::class, 'index'])->defaults('module', 'manure')->name('ui.manure');
Route::middleware(['auth', 'workspace'])->post('/manure', [ModuleController::class, 'storeProductProduction'])->defaults('module', 'manure')->name('manure.production.store');

// Names used by the copied Blade screens; API integration is being retained separately.
Route::middleware(['auth', 'workspace'])->get('/admin/carabaos', fn () => DB::table('carabaos')->orderByDesc('id')->get())->name('admin.carabaos.index');
Route::middleware(['auth', 'workspace'])->get('/admin/carabaos/options', fn () => response()->json([
    'cooperatives' => DB::table('cooperatives')->orderBy('cooperative_name')->get(),
    'farmers' => DB::table('farmers')->orderBy('last_name')->get(),
]))->name('admin.carabaos.options');
Route::middleware(['auth', 'workspace'])->get('/admin/carabao-inventory-api', function () {
    $cooperatives = DB::table('cooperatives')->get(['id', 'cooperative_code', 'cooperative_name', 'barangay', 'municipality', 'province'])->keyBy('id');

    return DB::table('carabaos')
        ->leftJoin('fhpr_records as fhpr', 'fhpr.carabao_id', '=', 'carabaos.id')
        ->select('carabaos.*', 'fhpr.days_in_milk')
        ->addSelect(['has_milk_production' => DB::table('milk_records')
            ->selectRaw('1')->whereColumn('milk_records.carabao_id', 'carabaos.id')->where('total_liters', '>', 0)->limit(1)])
        ->orderByDesc('carabaos.id')->get()->each(function ($animal) use ($cooperatives) {
            $animal->live_breedable_female = \App\Support\InventoryClassification::isLiveBreedableFemale($animal);
            \App\Support\CalfInventoryDetails::displayMissingFields($animal, $cooperatives->get($animal->cooperative_id));
        });
});
Route::middleware(['auth', 'workspace'])->get('/admin/calf-drop-api', function () {
    return DB::table('calves as calf')
        ->leftJoin('carabaos as dam', 'dam.id', '=', 'calf.dam_id')
        ->leftJoin('carabaos as child', 'child.animal_id', '=', 'calf.calf_animal_id')
        ->leftJoin('farmers as farmer', 'farmer.id', '=', 'dam.farmer_id')
        ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(dam.cooperative_id, farmer.cooperative_id)'))
        ->orderByDesc('calf.birth_date')->orderByDesc('calf.id')
        ->get([
            'calf.id', 'calf.calf_animal_id', 'calf.sire_id', 'calf.birth_date', 'calf.sex', 'calf.birth_weight',
            'calf.monitoring_date', 'calf.birth_outcome', 'calf.animal_status', 'calf.calf_findings', 'calf.remarks', 'calf.dam_id', 'calf.workbook_details',
            'dam.animal_id as dam_animal_id', 'dam.animal_name as dam_name', 'dam.breed as dam_breed', 'child.breed as calf_breed',
            'cooperative.cooperative_name', 'cooperative.province', 'cooperative.municipality', 'cooperative.barangay',
            'farmer.first_name', 'farmer.middle_name', 'farmer.last_name',
        ])
        ->map(function ($row) {
            $workbook = json_decode($row->workbook_details ?? '{}', true);
            return [
            'id' => $row->id, 'calf_id' => $row->calf_animal_id, 'mother_id' => $row->dam_animal_id ?: ($workbook['dam'] ?? null),
            'needs_review' => $row->workbook_details !== null && (! $row->dam_id || ! $row->birth_date || ! $row->sex),
            'dam_unlinked' => $row->workbook_details !== null && ! $row->dam_id,
            'farmer' => trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name]))) ?: ($workbook['farmer'] ?? ''),
            'province' => $row->province ?: ($workbook['province'] ?? null), 'cooperative' => $row->cooperative_name ?: ($workbook['cooperative'] ?? null), 'date_of_birth' => $row->birth_date,
            'municipality' => $row->municipality, 'barangay' => $row->barangay,
            'sex' => $row->sex, 'breed' => $row->calf_breed ?? $workbook['breed'] ?? $row->dam_breed, 'sire_id' => $row->sire_id,
            'monitored_date' => $row->monitoring_date, 'reported_year' => $row->birth_date ? substr($row->birth_date, 0, 4) : ($workbook['year'] ?? ''),
            'birth_outcome' => $row->birth_outcome, 'animal_status' => $row->animal_status,
            'birth_weight' => $row->birth_weight,
            'remarks' => trim(implode(' · ', array_filter([$row->calf_findings, $row->remarks]))),
            ];
        });
})->name('admin.calf-drop.records');
Route::middleware(['auth', 'workspace'])->patch('/admin/calf-drop-api/inventory/{id}', function (Request $request, int $id) {
    $data = $request->validate([
        'date_of_birth' => ['required', 'date'],
        'sex' => ['required', 'in:Male,Female'],
        'animal_status' => ['required', 'in:Live,Out,Dead,Not Applicable'],
        'breed' => ['nullable', 'string', 'max:255'],
        'sire_id' => ['nullable', 'string', 'max:255'],
    ]);
    $animal = DB::table('carabaos')->where('id', $id)->first();
    abort_if(! $animal || empty($animal->dam_id), 404, 'Calf inventory record was not found.');
    DB::table('carabaos')->where('id', $id)->update([
        'birth_date' => $data['date_of_birth'],
        'sex' => $data['sex'],
        'breed' => $data['breed'] ?? null,
        'sire_id' => $data['sire_id'] ?? null,
        'status' => $data['animal_status'],
        'updated_at' => now(),
    ]);
    return response()->json(['message' => 'Calf inventory record updated.']);
})->whereNumber('id')->name('admin.calf-drop.inventory.update');
Route::middleware(['auth', 'workspace'])->put('/admin/calf-drop-api/{id}', function (Request $request, int $id) {
    $record = DB::table('calves')->find($id);
    abort_unless($record, 404, 'Calf Drop record was not found.');
    $workbookEntry = $record->workbook_details !== null;
    $data = $request->validate([
        'date_of_birth' => [$workbookEntry ? 'nullable' : 'required', 'date'],
        'sex' => [$workbookEntry ? 'nullable' : 'required_if:birth_outcome,Live Birth', 'nullable', 'in:Male,Female'],
        'resolve_dam' => ['nullable', 'string', 'max:255'],
        'animal_status' => ['required', 'in:Live,Out,Dead,Not Applicable'],
        'birth_outcome' => ['required', 'in:Live Birth,Stillbirth,Abortion'],
        'monitored_date' => ['nullable', 'date'],
        'breed' => ['nullable', 'string', 'max:255'],
        'sire_id' => ['nullable', 'string', 'max:255'],
        'remarks' => ['nullable', 'string'],
    ]);
    $damId = $record->dam_id;
    if ($workbookEntry && ! $damId && filled($data['resolve_dam'] ?? null)) {
        $identifier = trim($data['resolve_dam']);
        $matches = DB::table('carabaos')->whereRaw("LOWER(TRIM(sex)) IN ('f', 'female')")
            ->where(fn ($query) => $query->where('animal_id', $identifier)->orWhere('registry_number', $identifier))
            ->limit(2)->pluck('id');
        abort_unless($matches->count() === 1, 422, 'Enter a Mother ID that uniquely matches a female animal in inventory.');
        $damId = $matches->first();
    }
    DB::table('calves')->where('id', $id)->update([
        'dam_id' => $damId,
        'birth_date' => $data['date_of_birth'] ?? null,
        'sex' => $data['sex'] ?? null,
        'birth_outcome' => $data['birth_outcome'],
        'animal_status' => $data['animal_status'],
        'monitoring_date' => $data['monitored_date'] ?? null,
        'sire_id' => $data['sire_id'] ?? null,
        'remarks' => $data['remarks'] ?? null,
        'updated_at' => now(),
    ]);
    return response()->json(['message' => 'Calf Drop record updated.']);
})->whereNumber('id')->name('admin.calf-drop.database.update');
Route::middleware(['auth', 'workspace'])->delete('/admin/calf-drop-api/{id}', function (int $id) {
    abort_unless(DB::table('calves')->where('id', $id)->delete(), 404, 'Calf record was not found.');

    return response()->noContent();
})->whereNumber('id')->name('admin.calf-drop.database.delete');
Route::middleware(['auth', 'workspace'])->delete('/admin/calf-drop-api/inventory/{id}', function (int $id) {
    DB::transaction(function () use ($id) {
        $animal = DB::table('carabaos')->where('id', $id)->lockForUpdate()->first();
        $dam = strtolower(trim((string) ($animal->dam_id ?? '')));
        abort_if(in_array($dam, ['', '-', '--', 'n/a', 'na', 'none', 'unknown', '0'], true), 404, 'Calf inventory record was not found.');

        $linked = DB::table('calves')->where('dam_id', $id)->exists();
        if ($animal->animal_id) {
            $linked = $linked || DB::table('calves')->where('calf_animal_id', $animal->animal_id)->exists()
                || DB::table('carabaos')->where('dam_id', $animal->animal_id)->orWhere('sire_id', $animal->animal_id)->exists();
        }
        foreach (['mating_records', 'milk_records', 'health_records', 'nature_of_exits', 'semen_usage', 'mtd_records', 'fhpr_records', 'bcs_records', 'monitoring_report_details'] as $table) {
            if ($linked) break;
            $linked = Schema::hasTable($table) && DB::table($table)->where('carabao_id', $id)->exists();
        }
        abort_if($linked, 409, 'This inventory animal has linked records and cannot be deleted from Calf Records.');
        DB::table('carabaos')->where('id', $id)->delete();
    });

    return response()->noContent();
})->whereNumber('id')->name('admin.calf-drop.inventory.delete');
Route::middleware(['auth', 'workspace:monitoring-entry'])->get('/admin/calf-drop-api/series', function (Request $request) {
    $data = $request->validate([
        'dam_id' => ['required', 'integer', 'exists:carabaos,id'],
        'cooperative_id' => ['nullable', 'integer', 'exists:cooperatives,id'],
        'herd_code' => ['nullable', 'string', 'max:16'],
        'breed' => ['required', 'string', 'max:255'],
        'date_of_birth' => ['required', 'date'],
    ]);
    $dam = DB::table('carabaos as animal')
        ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
        ->where('animal.id', $data['dam_id'])
        ->first(['animal.cooperative_id', 'animal.farmer_id', 'animal.registry_number', 'animal.animal_id', 'farmer.cooperative_id as farmer_cooperative_id']);
    abort_unless($dam, 404);
    $cooperativeId = $data['cooperative_id'] ?? $dam->cooperative_id ?? $dam->farmer_cooperative_id;
    $herdCode = \App\Support\CooperativeReportingCounts::calfHerdCode(
        DB::table('cooperatives')->where('id', $cooperativeId)->value('cooperative_code'), $data['herd_code'] ?? null, $dam->farmer_id,
        $dam->registry_number ?: $dam->animal_id);

    return response()->json(\App\Support\CalfRegistryId::sequence($data['breed'], $herdCode, $data['date_of_birth']));
})->name('admin.calf-drop.series');

Route::middleware(['auth', 'workspace'])->post('/admin/calf-drop-api', function (Request $request) {
    $data = $request->validate([
        'calf_id' => ['nullable', 'string', 'max:255'],
        'registry_series' => ['nullable', 'integer', 'between:1,999'],
        'herd_code' => ['nullable', 'string', 'max:16'],
        'mother_id' => ['required', 'string', 'max:255'],
        'cooperative_id' => ['nullable', 'required_with:farmer_id', 'integer', 'exists:cooperatives,id'],
        'farmer_id' => ['nullable', 'required_with:cooperative_id', 'integer', 'exists:farmers,id'],
        'date_of_birth' => ['required', 'date'],
        'birth_outcome' => ['sometimes', 'in:Live Birth,Stillbirth,Abortion'],
        'monitored_date' => ['nullable', 'date'],
        'sex' => ['required_unless:birth_outcome,Stillbirth,Abortion', 'nullable', 'in:Male,Female'],
        'animal_status' => ['nullable', 'in:Live,Out,Dead,Not Applicable'],
        'breed' => ['nullable', 'string', 'max:255'],
        'sire_id' => ['nullable', 'string', 'max:255'],
        'birth_place' => ['nullable', 'string', 'max:255'],
        'breeder' => ['nullable', 'string', 'max:255'],
        'association_coop_institution' => ['nullable', 'string', 'max:255'],
        'barangay' => ['nullable', 'string', 'max:255'],
        'municipality' => ['nullable', 'string', 'max:255'],
        'province' => ['nullable', 'string', 'max:255'],
        'district' => ['nullable', 'string', 'max:255'],
        'region' => ['nullable', 'string', 'max:255'],
        'remarks' => ['nullable', 'string'],
    ]);

    $motherId = trim($data['mother_id']);
    $farmerId = $data['farmer_id'] ?? null;
    $cooperativeId = $data['cooperative_id'] ?? null;
    if ($farmerId !== null && DB::table('cooperatives')->where('id', $cooperativeId)->value('cooperative_code') === 'AST') {
        $members = \App\Support\CooperativeReportingCounts::whereAnimalMembership(
            DB::table('carabaos as animal')->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id'), (int) $cooperativeId);
        abort_unless($members->where('animal.farmer_id', $farmerId)
            ->where(fn ($query) => $query->where('animal.animal_id', $motherId)->orWhere('animal.registry_number', $motherId))->exists(),
            422, 'The selected dam does not belong to an Animal Industry herd.');
    } elseif ($farmerId !== null) {
        abort_unless(DB::table('farmers')->where('id', $farmerId)->where('cooperative_id', $cooperativeId)->exists(),
            422, 'The selected farmer does not belong to this cooperative.');
    }

    $mother = DB::table('carabaos')
        ->when($farmerId !== null, fn ($query) => $query->where('farmer_id', $farmerId))
        ->where(function ($query) use ($motherId) {
            if (ctype_digit($motherId)) {
                $query->where('id', (int) $motherId);
            }
            $query->orWhere('animal_id', $motherId)
                ->orWhere('registry_number', $motherId)
                ->orWhere('bolus_id', $motherId)
                ->orWhere('tag_number', $motherId);
        })
        ->first();

    abort_if(! $mother, 422, $farmerId !== null
        ? 'Mother ID was not found in the selected farmer’s inventory.'
        : 'Mother ID was not found in Carabao Inventory.');

    if ($farmerId !== null) {
        $mother->days_in_milk = DB::table('fhpr_records')->where('carabao_id', $mother->id)->value('days_in_milk');
        abort_unless(\App\Support\InventoryClassification::isLiveBreedableFemale($mother), 422,
            'Manual entry requires a live, breedable female from Carabao Inventory.');
    }

    $animalId = trim((string) ($data['calf_id'] ?? ''));
    [$breed, $sireId] = \App\Support\CalfBreedSeries::resolveParentage($mother->breed, $data['sire_id'] ?? null, $data['breed'] ?? null);
    $cooperativeId ??= $mother->cooperative_id ?? DB::table('farmers')->where('id', $mother->farmer_id)->value('cooperative_id');
    $outcome = $data['birth_outcome'] ?? 'Live Birth';
    $selectedSeries = isset($data['registry_series']) ? (int) $data['registry_series'] : null;
    abort_if($selectedSeries !== null && ($animalId !== '' || $outcome !== 'Live Birth'), 422,
        'Series selection is available when assigning a Calf ID for a live birth.');
    if ($outcome !== 'Live Birth') {
        $id = DB::transaction(function () use ($animalId, $data, $mother, $outcome, $sireId) {
            DB::table('carabaos')->where('id', $mother->id)->lockForUpdate()->first();
            $date = date('Y-m-d', strtotime($data['date_of_birth']));
            abort_if(DB::table('calves')->where('dam_id', $mother->id)->where('birth_date', $date)->exists(),
                422, 'This dam already has a calf drop recorded for that date.');
            abort_if($animalId !== '' && (DB::table('carabaos')->where('animal_id', $animalId)->orWhere('registry_number', $animalId)->exists()
                || DB::table('calves')->where('calf_animal_id', $animalId)->exists()), 422, 'Calf ID already exists.');

            return DB::table('calves')->insertGetId([
                'calf_animal_id' => $animalId ?: null,
                'dam_id' => $mother->id,
                'sire_id' => $sireId,
                'birth_date' => $date,
                'monitoring_date' => $data['monitored_date'] ?? null,
                'sex' => $data['sex'] ?? null,
                'birth_outcome' => $outcome,
                'animal_status' => $outcome === 'Stillbirth' ? 'Dead' : 'Not Applicable',
                'remarks' => $data['remarks'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['message' => 'Calf drop recorded.', 'id' => $id], 201);
    }

    $id = DB::transaction(function () use ($animalId, $breed, $cooperativeId, $data, $farmerId, $mother, $sireId, $selectedSeries) {
        \App\Support\CalfRegistryId::lockSequence();
        $cooperative = $cooperativeId === null
            ? null
            : DB::table('cooperatives')->where('id', $cooperativeId)->lockForUpdate()->first();

        if ($farmerId !== null) {
            $damIds = array_filter([$mother->id, $mother->animal_id, $mother->registry_number, $mother->bolus_id, $mother->tag_number]);
            $birthDate = date('Y-m-d', strtotime($data['date_of_birth']));
            abort_if(DB::table('calves')->where('dam_id', $mother->id)->where('birth_date', $birthDate)->exists()
                || DB::table('carabaos')->whereIn('dam_id', $damIds)->where('birth_date', $birthDate)->exists(), 422, 'This dam already has a calf drop recorded for that date.');
        }

        if ($animalId === '') {
            $herdCode = \App\Support\CooperativeReportingCounts::calfHerdCode($cooperative?->cooperative_code, $data['herd_code'] ?? null,
                $mother->farmer_id, $mother->registry_number ?: $mother->animal_id);
            $animalId = \App\Support\CalfRegistryId::next($breed, $herdCode, $data['date_of_birth'], $selectedSeries);
        }

        abort_if(DB::table('carabaos')->where('animal_id', $animalId)->orWhere('registry_number', $animalId)->exists()
            || DB::table('calves')->where('calf_animal_id', $animalId)->exists(), 422, 'Calf ID already exists.');

        return DB::table('carabaos')->insertGetId(\App\Support\CalfInventoryDetails::forBirth($animalId, $cooperative, $mother, $data) + [
            'animal_id' => $animalId,
            'farmer_id' => $mother->farmer_id,
            'cooperative_id' => $cooperativeId,
            'animal_name' => null,
            'sex' => $data['sex'],
            'breed' => $breed,
            'classification' => $data['sex'] === 'Male' ? 'Male Calf' : 'Female Calf',
            'birth_date' => $data['date_of_birth'],
            'status' => $data['animal_status'] ?? 'Live',
            'sire_id' => $sireId,
            'dam_id' => $mother->animal_id,
            'dam_name' => $mother->animal_name,
            'barangay' => $data['barangay'] ?? $mother->barangay,
            'municipality' => $data['municipality'] ?? $mother->municipality,
            'province' => $data['province'] ?? $mother->province,
            'district' => $data['district'] ?? $mother->district,
            'region' => $data['region'] ?? $mother->region,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }, 3);

    return response()->json([
        'message' => 'Calf added to Carabao Inventory.',
        'animal' => DB::table('carabaos')->where('id', $id)->first(),
    ], 201);
})->name('admin.calf-drop.store');
Route::middleware(['auth', 'workspace'])->patch('/admin/carabao-inventory-api/{id}/classification', function (\Illuminate\Http\Request $request, int $id) {
    $data = $request->validate(['classification' => ['nullable', 'string', 'max:255']]);
    $animal = DB::table('carabaos')->where('id', $id)->first();
    abort_unless($animal, 404, 'Carabao record not found.');
    $updated = DB::table('carabaos')->where('id', $id)->update([
        'classification' => \App\Support\InventoryClassification::isExcluded($animal->status, $animal->nature_of_exit) ? null : ($data['classification'] ?? null),
        'updated_at' => now(),
    ]);
    abort_if($updated === 0 && ! DB::table('carabaos')->where('id', $id)->exists(), 404, 'Carabao record not found.');
    return response()->json(['message' => 'Classification updated.']);
})->name('admin.carabaos.classification');
Route::middleware(['auth', 'workspace'])->get('/admin/semen-bulls-api', fn () => DB::table('semen_bulls')->orderBy('bull_name')->get());
Route::middleware(['auth', 'workspace'])->get('/admin/health-nutrition-api', function () {
    return DB::table('health_records as record')
        ->leftJoin('carabaos as animal', 'animal.id', '=', 'record.carabao_id')
        ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
        ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(record.cooperative_id, animal.cooperative_id, farmer.cooperative_id)'))
        ->orderByDesc('record.monitoring_date')->orderByDesc('record.id')
        ->get(['record.*','animal.animal_id','animal.bolus_id','animal.registry_number as animal_registry_number','cooperative.cooperative_name','cooperative.province','farmer.first_name','farmer.middle_name','farmer.last_name'])
        ->map(function ($row) {
            $source = json_decode((string) $row->source_data, true) ?: [];
            $fields = $source['monitoring_fields'] ?? [];
            $services = $source['services'] ?? $fields['services'] ?? [];
            $serviceLines = collect($services)->map(function ($service) {
                if (! is_array($service)) return null;
                $name = ($service['type'] ?? '') === 'Other Services'
                    ? 'Other Services: '.trim((string) ($service['other_service_name'] ?? ''))
                    : trim((string) ($service['type'] ?? ''));
                $dosage = ($service['dosage_ml'] ?? '') !== '' && ($service['dosage_ml'] ?? null) !== null
                    ? $service['dosage_ml'].' mL'
                    : null;
                return trim(implode(' — ', array_filter([$name, $dosage, trim((string) ($service['remarks'] ?? ''))])));
            })->filter()->values()->all();
            if ($serviceLines === [] && ! empty($source['service_type'])) {
                $serviceLines[] = trim(implode(' — ', array_filter([$source['service_type'], $source['service_description'] ?? null])));
            }
            return [
                'id' => 'db-health-'.$row->id, 'database_id' => $row->id, 'source' => 'MySQL',
                'animal_id' => $row->animal_registry_number ?: ($row->animal_id ?: 'N/A'),
                'farmer' => trim(implode(' ', array_filter([$row->first_name,$row->middle_name,$row->last_name]))),
                'province' => $row->province, 'cooperative' => $row->cooperative_name,
                'monitoring_date' => $row->monitoring_date, 'health_status' => $row->health_status,
                'condition' => $row->findings, 'feed' => $fields['feed'] ?? '', 'supplement' => $fields['medication'] ?? '',
                'treatment' => $row->treatment, 'body_condition' => $fields['body_condition'] ?? '',
                'temperature' => $fields['temperature'] ?? '', 'services' => implode('; ', $serviceLines), 'remarks' => $row->remarks,
                'record_category' => $row->record_category,
            ];
        });
})->name('admin.health-nutrition.records');
Route::middleware(['auth', 'workspace'])->get('/admin/health-records/{record}', [ModuleController::class, 'healthRecord'])->whereNumber('record')->name('admin.health-records.show');
Route::middleware(['auth', 'workspace'])->post('/admin/health-records', [ModuleController::class, 'storeHealthRecord'])->name('admin.health-records.store');
Route::middleware(['auth', 'workspace'])->put('/admin/health-records/{record}', [ModuleController::class, 'updateHealthRecord'])->whereNumber('record')->name('admin.health-records.update');
Route::middleware(['auth', 'workspace'])->delete('/admin/health-records/{record}', [ModuleController::class, 'deleteHealthRecord'])->whereNumber('record')->name('admin.health-records.delete');
Route::middleware(['auth', 'workspace'])->post('/admin/carabaos', fn () => response()->json(['message' => 'Carabao API is not connected yet.'], 501))->name('admin.carabaos.store');
Route::middleware(['auth', 'workspace'])->post('/admin/carabaos/bulk', fn () => response()->json(['message' => 'Carabao API is not connected yet.'], 501))->name('admin.carabaos.bulk');
Route::middleware(['auth', 'workspace'])->post('/admin/carabaos/import', fn () => response()->json(['message' => 'Use the cooperative workbook import.'], 501))->name('admin.carabaos.import');
Route::middleware(['auth', 'workspace'])->get('/admin/cooperatives/data', fn () => \App\Support\CooperativeReportingCounts::records())->name('admin.cooperatives.data');
Route::middleware(['auth', 'workspace'])->post('/admin/cooperatives', function (Request $request) {
    $data = $request->validate([
        'cooperative_code' => ['required', 'string', 'max:255', 'unique:cooperatives,cooperative_code'],
        'cooperative_name' => ['required', 'string', 'max:255'],
        'cda_registration_no' => ['nullable', 'string', 'max:255'],
        'accreditation_status' => ['nullable', 'string', 'max:255'],
        'coop_type' => ['nullable', 'string', 'max:255'],
        'category' => ['nullable', 'string', 'max:255'],
        'date_established' => ['nullable', 'date'],
        'region' => ['nullable', 'string', 'max:255'],
        'province' => ['nullable', 'string', 'max:255'],
        'municipality' => ['nullable', 'string', 'max:255'],
        'barangay' => ['nullable', 'string', 'max:255'],
        'year_enrolled' => ['nullable', 'integer', 'min:1900', 'max:2200'],
    ]);

    $now = now();
    $id = DB::table('cooperatives')->insertGetId([
        'cooperative_code' => trim($data['cooperative_code']),
        'cooperative_name' => trim($data['cooperative_name']),
        'cda_registration_no' => $data['cda_registration_no'] ?? null,
        'accreditation_status' => $data['accreditation_status'] ?? null,
        'coop_type' => $data['coop_type'] ?? null,
        'category' => $data['category'] ?? null,
        'date_established' => $data['date_established'] ?? null,
        'region' => $data['region'] ?? null,
        'province' => $data['province'] ?? null,
        'municipality' => $data['municipality'] ?? null,
        'barangay' => $data['barangay'] ?? null,
        'year_enrolled' => $data['year_enrolled'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return response()->json([
        'message' => 'Cooperative added and saved to database.',
        'data' => DB::table('cooperatives')->find($id),
    ], 201);
})->name('admin.cooperatives.store');
Route::middleware(['auth', 'workspace'])->put('/cooperatives/{id}', function (Request $request, $id) {
    $data = $request->all();

    DB::table('cooperatives')->where('id', $id)->update([
        'cda_registration_no' => $data['cda_registration_no'] ?? null,
        'cooperative_code' => $data['cooperative_code'] ?? null,
        'cooperative_name' => $data['cooperative_name'] ?? null,
        'accreditation_status' => $data['accreditation_status'] ?? null,
        'coop_type' => $data['coop_type'] ?? null,
        'category' => $data['category'] ?? null,
        'date_established' => ! empty($data['date_established']) ? $data['date_established'] : null,
        'region' => $data['region'] ?? null,
        'province' => $data['province'] ?? null,
        'municipality' => $data['municipality'] ?? null,
        'barangay' => $data['barangay'] ?? null,
        'year_enrolled' => ! empty($data['year_enrolled']) ? $data['year_enrolled'] : null,
        'updated_at' => now(),
    ]);

    return response()->json(['message' => 'Cooperative updated.']);
})->name('cooperatives.update');
Route::middleware(['auth', 'workspace'])->get('/admin/farmers/data', fn () => DB::table('farmers')->orderBy('last_name')->get()->map(function ($farmer) {
    $farmer->cooperative_record = $farmer->cooperative_id ? DB::table('cooperatives')->find($farmer->cooperative_id) : null;
    return $farmer;
}))->name('admin.farmers.data');
Route::middleware(['auth', 'workspace'])->post('/admin/farmers', fn () => response()->json(['message' => 'Farmer API is not connected yet.'], 501))->name('admin.farmers.store');
Route::middleware(['auth', 'workspace'])->post('/admin/farmers/bulk', fn () => response()->json(['message' => 'Farmer API is not connected yet.'], 501))->name('admin.farmers.bulk');
Route::middleware(['auth', 'workspace'])->post('/admin/farmers/import', [FarmerSpreadsheetController::class, 'import'])->name('admin.farmers.import');
Route::middleware(['auth', 'workspace:admin'])->get('/admin/farmers/template', [FarmerSpreadsheetController::class, 'template'])->name('admin.farmers.template');
Route::middleware(['auth', 'workspace'])->get('/admin/breeding-api/records', fn () => DB::table('mating_records as mr')
    ->leftJoin('carabaos as a', 'a.id', '=', 'mr.carabao_id')
    ->leftJoin('farmers as f', 'f.id', '=', 'a.farmer_id')
    ->leftJoin('cooperatives as c', 'c.id', '=', DB::raw('COALESCE(mr.cooperative_id, a.cooperative_id, f.cooperative_id)'))
    ->leftJoin('cooperatives as farmer_coop', 'farmer_coop.id', '=', 'f.cooperative_id')
    ->leftJoin('semen_bulls as sb', 'sb.id', '=', 'mr.semen_bull_id')
    ->select('mr.*', 'c.cooperative_name', 'a.animal_name', 'a.animal_id as animal_registry_id', 'a.status as inventory_status', 'a.nature_of_exit as inventory_exit', 'f.id as farmer_id', 'f.first_name as farmer_first_name', 'f.middle_name as farmer_middle_name', 'f.last_name as farmer_last_name', 'sb.bull_name as matched_bull_name')
    ->selectRaw("COALESCE(NULLIF(TRIM(a.province), ''), farmer_coop.province, c.province) as farmer_province")
    ->selectRaw("COALESCE(NULLIF(TRIM(a.municipality), ''), farmer_coop.municipality, c.municipality) as farmer_municipality")
    ->selectRaw("COALESCE(NULLIF(TRIM(a.barangay), ''), farmer_coop.barangay, c.barangay) as farmer_barangay")
    ->orderByDesc('mr.id')->get()->map(function ($record) {
        $source = json_decode((string) $record->source_data, true) ?: [];
        $fields = $source['mating_api_fields'] ?? $source['monitoring_fields'] ?? [];
        $record->straws_used = $fields['straws_used'] ?? $source['semen_used'] ?? null;
        $record->bull_name = $fields['bull_name'] ?? $record->matched_bull_name ?? null;
        $record->estrus_type = $record->estrus_type ?? $fields['estrus_type'] ?? null;
        $record->pregnancy_months = $fields['pregnancy_months'] ?? \App\Support\MatingWorksheetMapping::pregnancyMonthsFromSource($source);
        $record->pd_technician = $fields['pd_technician'] ?? \App\Support\MatingWorksheetMapping::pdTechnicianFromSource($source);
        $record->record_date = $source['record_date'] ?? $source['monitoring_date'] ?? null;
        $record->pregnancy_status = $source['pregnancy_status'] ?? null;
        $record->farmer_id = $record->farmer_id ?? $fields['farmer_id'] ?? null;
        $record->animal_id = $record->animal_registry_id ?: $record->registry_number;
        return $record;
    })->pipe(fn ($records) => \App\Support\MatingCalfReports::annotate(
        $records, DB::table('calves')->whereIn('dam_id', $records->pluck('carabao_id')->filter()->unique())->get(['dam_id', 'birth_date']),
        DB::table('carabaos')->whereNotNull('dam_id')->get(['dam_id as mother_id', 'birth_date'])
    ))->pipe(fn($records)=>\App\Support\OverdueCalfNotifications::annotate($records)))->name('admin.breeding-api.records');
Route::middleware(['auth', 'workspace'])->get('/admin/breeding-api/cooperatives', fn () => DB::table('cooperatives')->orderBy('cooperative_name')->get())->name('admin.breeding-api.cooperatives');
Route::middleware(['auth', 'workspace'])->get('/admin/breeding-api/farmers', fn () => DB::table('farmers')->orderBy('last_name')->get())->name('admin.breeding-api.farmers');
Route::middleware(['auth', 'workspace'])->get('/admin/breeding-api/carabaos', fn () => DB::table('carabaos')->orderBy('animal_name')->get())->name('admin.breeding-api.carabaos');
Route::middleware(['auth', 'workspace'])->get('/admin/breeding-api/semen-bulls', fn () => DB::table('semen_bulls')->orderBy('bull_name')->get())->name('admin.breeding-api.semen-bulls');
$matingApiRules = [
    'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
    'farmer_id' => ['required', 'integer', 'exists:farmers,id'],
    'carabao_id' => ['required', 'integer', 'exists:carabaos,id'],
    'breeding_date' => ['required', 'date'],
    'mating_type' => ['required', 'in:AI,NM,Natural Mating'],
    'semen_bull_id' => ['nullable', 'integer', 'exists:semen_bulls,id'],
    'bull_id' => ['nullable', 'string', 'max:255'], 'bull_name' => ['nullable', 'string', 'max:255'],
    'straws_used' => ['nullable', 'numeric', 'min:0'], 'estrus_type' => ['nullable', 'string', 'max:100'],
    'mating_technician' => ['nullable', 'string', 'max:255'], 'pd_date' => ['nullable', 'date'],
    'pd_result' => ['nullable', 'in:Pending,Positive,Negative'], 'pregnancy_months' => ['nullable', 'integer', 'min:0', 'max:11'],
    'pd_technician' => ['nullable', 'string', 'max:255'], 'expected_calving_date' => ['nullable', 'date'],
    'actual_calving_date' => ['nullable', 'date'], 'remarks' => ['nullable', 'string', 'max:2000'],
];
$pendingMatingPdValues = ['', 'pending', 'null', 'n/a', 'na', '?'];
$persistMatingRecord = function (array $data, ?int $recordId = null) {
    $animal = DB::table('carabaos as animal')->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
        ->where('animal.id', (int) $data['carabao_id'])
        ->first(['animal.*', 'farmer.cooperative_id as farmer_cooperative_id']);
    abort_unless($animal, 422, 'The selected animal is not in Carabao Inventory.');
    $actualCooperativeId = (int) ($animal->cooperative_id ?: $animal->farmer_cooperative_id);
    abort_unless($actualCooperativeId === (int) $data['cooperative_id'], 422, 'The animal does not belong to the selected cooperative.');
    abort_unless((int) $animal->farmer_id === (int) $data['farmer_id'], 422, 'The selected farmer does not own this animal.');

    $existing = $recordId ? DB::table('mating_records')->where('id', $recordId)->first() : null;
    if ($recordId) abort_unless($existing, 404, 'Mating record not found.');
    $source = $existing ? (json_decode((string) $existing->source_data, true) ?: []) : [];
    if (! is_array($source)) {
        $source = $existing && $existing->source_data
            ? ['original_source_data' => $existing->source_data]
            : [];
    }
    $source['mating_api_fields'] = [
        'farmer_id' => (int) $data['farmer_id'], 'bull_name' => $data['bull_name'] ?? null,
        'straws_used' => $data['straws_used'] ?? null, 'estrus_type' => $data['estrus_type'] ?? null,
        'pregnancy_months' => $data['pregnancy_months'] ?? null, 'pd_technician' => $data['pd_technician'] ?? null,
    ];
    $bull = ! empty($data['semen_bull_id']) ? DB::table('semen_bulls')->where('id', $data['semen_bull_id'])->first() : null;
    $fields = [
        'carabao_id' => $animal->id, 'cooperative_id' => $actualCooperativeId, 'registry_number' => $animal->registry_number ?: $animal->animal_id,
        'mating_date' => $data['breeding_date'], 'mating_type' => in_array($data['mating_type'], ['NM', 'Natural Mating'], true) ? 'NM' : 'AI',
        'bull_id' => ($data['bull_id'] ?? null) ?: ($bull->bull_id ?? null), 'semen_bull_id' => $bull->id ?? null,
        'technician' => $data['mating_technician'] ?? null, 'pregnancy_check_date' => $data['pd_date'] ?? null,
        'pregnancy_result' => $data['pd_result'] ?? 'Pending', 'expected_calving_date' => $data['expected_calving_date'] ?? null,
        'actual_calving_date' => $data['actual_calving_date'] ?? null, 'remarks' => $data['remarks'] ?? null,
        'source_data' => json_encode($source, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
    ];
    if ($existing) {
        DB::table('mating_records')->where('id', $existing->id)->update($fields);
        \App\Support\FhprClassification::syncAnimal((int)$animal->id);
        return (int) $existing->id;
    }
    $id=(int) DB::table('mating_records')->insertGetId($fields + ['created_at' => now()]);
    \App\Support\FhprClassification::syncAnimal((int)$animal->id);
    return $id;
};
Route::middleware(['auth', 'workspace'])->post('/admin/breeding-api/records', function (Request $request) use ($matingApiRules, $persistMatingRecord) {
    $data = $request->validate($matingApiRules);
    $id = DB::transaction(fn () => $persistMatingRecord($data));
    return response()->json(['message' => 'Mating record saved.', 'id' => $id], 201);
})->name('admin.breeding-api.store');
Route::middleware(['auth', 'workspace'])->post('/admin/breeding-api/bulk-store', function (Request $request) use ($matingApiRules, $persistMatingRecord, $pendingMatingPdValues) {
    $rules = ['cooperative_id' => $matingApiRules['cooperative_id'], 'records' => ['required', 'array', 'min:1', 'max:1000'], 'check_pending_pd' => ['sometimes', 'boolean']];
    foreach ($matingApiRules as $field => $fieldRules) if ($field !== 'cooperative_id') $rules['records.*.'.$field] = $fieldRules;
    $data = $request->validate($rules);
    $ids = DB::transaction(function () use ($data, $persistMatingRecord, $pendingMatingPdValues) {
        if (! empty($data['check_pending_pd'])) {
            $animalIds = collect($data['records'])->pluck('carabao_id')->unique();
            $animals = DB::table('carabaos')->whereIn('id', $animalIds)->orderBy('id')->lockForUpdate()
                ->get(['id', 'animal_id', 'registry_number']);
            $registryIds = $animals->flatMap(fn ($animal) => [$animal->registry_number, $animal->animal_id])
                ->map(fn ($identifier) => strtolower(trim((string) $identifier)))->filter()->unique()->values();
            $pending = DB::table('mating_records')
                ->whereIn(DB::raw("LOWER(TRIM(COALESCE(pregnancy_result, '')))"), $pendingMatingPdValues)
                ->where(fn ($query) => $query->whereIn('carabao_id', $animalIds)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('carabao_id')->where('cooperative_id', $data['cooperative_id'])
                        ->whereIn(DB::raw('LOWER(TRIM(registry_number))'), $registryIds)))
                ->lockForUpdate()->first(['registry_number', 'carabao_id']);
            abort_if($pending, 422, 'Update the pending PD result for '.($pending?->registry_number ?: 'the selected animal').' before saving.');
        }
        return collect($data['records'])->map(function ($record) use ($data, $persistMatingRecord) {
            $record['cooperative_id'] = $data['cooperative_id'];
            return $persistMatingRecord($record);
        })->all();
    });
    return response()->json(['message' => 'Mating records saved.', 'ids' => $ids], 201);
})->name('admin.breeding-api.bulk');
Route::middleware(['auth', 'workspace'])->patch('/admin/breeding-api/records/{id}/pd', function (Request $request, int $id) use ($pendingMatingPdValues) {
    $data = $request->validate([
        'pd_result' => ['required', 'in:Positive,Negative'],
        'pd_date' => ['required', 'date_format:Y-m-d'],
        'pd_technician' => ['required', 'string', 'max:255'],
        'pregnancy_months' => ['nullable', 'required_if:pd_result,Positive', 'integer', 'min:1', 'max:11'],
        'expected_calving_date' => ['nullable', 'date_format:Y-m-d'],
    ]);
    DB::transaction(function () use ($id, $data, $pendingMatingPdValues) {
        $record = DB::table('mating_records')->find($id);
        abort_unless($record, 404, 'Mating record not found.');
        if ($record->carabao_id) DB::table('carabaos')->where('id', $record->carabao_id)->lockForUpdate()->first(['id']);
        $record = DB::table('mating_records')->lockForUpdate()->find($id);
        abort_unless($record, 404, 'Mating record not found.');
        abort_unless(in_array(strtolower(trim((string) $record->pregnancy_result)), $pendingMatingPdValues, true), 409, 'This PD result was already updated. Refresh the records before trying again.');
        abort_if($record->mating_date && $data['pd_date'] < $record->mating_date, 422, 'The PD date must be on or after the mating date.');

        $positive = $data['pd_result'] === 'Positive';
        $source = json_decode((string) $record->source_data, true);
        $source = is_array($source) ? $source : ($record->source_data ? ['original_source_data' => $record->source_data] : []);
        $fields = $source['mating_api_fields'] ?? $source['monitoring_fields'] ?? [];
        $source['mating_api_fields'] = array_replace(is_array($fields) ? $fields : [], [
            'pd_technician' => $data['pd_technician'],
            'pregnancy_months' => $positive ? $data['pregnancy_months'] : null,
        ]);
        DB::table('mating_records')->where('id', $id)->update([
            'pregnancy_result' => $data['pd_result'],
            'pregnancy_check_date' => $data['pd_date'],
            'expected_calving_date' => $positive ? ($data['expected_calving_date'] ?? null) : null,
            'source_data' => json_encode($source, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
        if ($record->carabao_id) \App\Support\FhprClassification::syncAnimal((int) $record->carabao_id);
    });
    return response()->json(['message' => 'PD result updated.']);
})->whereNumber('id')->name('admin.breeding-api.pd');
Route::middleware(['auth', 'workspace'])->put('/admin/breeding-api/records/{id}', function (Request $request, int $id) use ($matingApiRules, $persistMatingRecord) {
    $data = $request->validate($matingApiRules);
    DB::transaction(fn()=>$persistMatingRecord($data, $id));
    return response()->json(['message' => 'Mating record updated.', 'id' => $id]);
})->whereNumber('id')->name('admin.breeding-api.update');
Route::middleware(['auth', 'workspace'])->delete('/admin/breeding-api/records/{id}', function (int $id) {
    abort_unless(DB::table('mating_records')->where('id', $id)->exists(), 404, 'Mating record not found.');
    DB::table('mating_records')->where('id', $id)->delete();
    return response()->json(['message' => 'Mating record deleted.']);
})->whereNumber('id')->name('admin.breeding-api.delete');

// CBED Modules
Route::middleware(['auth', 'workspace'])->get('/modules/{module}', [ModuleController::class, 'index'])->name('modules.index');
Route::middleware(['auth', 'workspace'])->post('/modules/{module}/import', [ModuleController::class, 'import'])->name('modules.import');
Route::middleware(['auth', 'workspace:admin'])->get('/modules/{module}/template', [ModuleController::class, 'template'])->name('modules.template');
Route::middleware(['auth', 'workspace'])->get('/modules/{module}/export', [ModuleController::class, 'export'])->name('modules.export');
