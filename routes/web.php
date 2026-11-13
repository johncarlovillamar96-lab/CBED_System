
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AthenaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\CooperativeImportController;
use App\Http\Controllers\CooperativeWorkbookImportController;
use App\Http\Controllers\CooperativeSpreadsheetController;
use App\Http\Controllers\FarmerSpreadsheetController;
use App\Http\Controllers\MonitoringReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

// Login routes
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/athena/chat', [AthenaController::class, 'chat'])->middleware('throttle:10,1')->name('athena.chat');

// Dashboard - temporary access without login
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.page');
Route::get('/imports/cooperative', [CooperativeImportController::class, 'create'])->name('imports.cooperative.create');
Route::post('/imports/cooperative/preview', [CooperativeImportController::class, 'preview'])->name('imports.cooperative.preview');
Route::post('/imports/cooperative/commit', [CooperativeImportController::class, 'import'])->name('imports.cooperative.import');
Route::get('/imports/cooperative-workbook', [CooperativeWorkbookImportController::class, 'create'])->name('imports.cooperative-workbook.create');
Route::post('/imports/cooperative-workbook', [CooperativeWorkbookImportController::class, 'import'])->name('imports.cooperative-workbook.import');
Route::post('/imports/cooperative-workbook/batches', [CooperativeWorkbookImportController::class, 'createBatch'])->name('imports.cooperative-workbook.batches.create');
Route::post('/imports/cooperative-workbook/batches/{batchId}/files', [CooperativeWorkbookImportController::class, 'stageBatchFile'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.files.stage');
Route::post('/imports/cooperative-workbook/batches/{batchId}/confirm', [CooperativeWorkbookImportController::class, 'confirmBatch'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.confirm');
Route::post('/imports/cooperative-workbook/unmapped/{identifierId}/map', [CooperativeWorkbookImportController::class, 'mapUnmappedIdentifier'])
    ->whereNumber('identifierId')->name('imports.cooperative-workbook.unmapped.map');
Route::post('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}/import', [CooperativeWorkbookImportController::class, 'importBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.import');
Route::post('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}/retry', [CooperativeWorkbookImportController::class, 'retryBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.retry');
Route::delete('/imports/cooperative-workbook/batches/{batchId}/files/{fileId}', [CooperativeWorkbookImportController::class, 'removeBatchFile'])
    ->whereNumber('batchId')->whereNumber('fileId')->name('imports.cooperative-workbook.batches.files.remove');
Route::delete('/imports/cooperative-workbook/batches/{batchId}', [CooperativeWorkbookImportController::class, 'cancelBatch'])
    ->whereNumber('batchId')->name('imports.cooperative-workbook.batches.cancel');
Route::post('/imports/cooperative-workbook/{cooperativeId}/{sheetKey}', [CooperativeWorkbookImportController::class, 'importSheet'])
    ->whereNumber('cooperativeId')
    ->name('imports.cooperative-workbook.import-sheet');
Route::delete('/imports/cooperative-workbook/history', [CooperativeWorkbookImportController::class, 'clearHistory'])
    ->name('imports.cooperative-workbook.clear-history');
Route::get('/admin/cooperatives/export', [CooperativeSpreadsheetController::class, 'export'])->name('admin.cooperatives.export');
Route::post('/admin/cooperatives/import', [CooperativeSpreadsheetController::class, 'import'])->name('admin.cooperatives.import');

// Original CBED_NEW Blade screens
Route::view('/farmers', 'farmer')->name('ui.farmers');
Route::view('/cooperatives', 'cooperative')->name('ui.cooperatives');
Route::view('/carabaos', 'carabao')->name('ui.carabaos');
Route::view('/mating', 'mating')->name('ui.mating');
Route::view('/calves', 'calf-drop')->name('ui.calves');
Route::redirect('/calf-drop', '/calves')->name('ui.calf-drop');
Route::view('/health-nutrition', 'health-nutrition')->name('ui.health-nutrition');
Route::view('/milk-production', 'milk-production')->name('ui.milk-production');
Route::view('/mil-test', 'mil-test')->name('ui.mil-test');
Route::get('/nature-of-exit', [ModuleController::class, 'natureOfExit'])->name('ui.nature-of-exit');
Route::view('/mapping', 'mapping')->name('ui.mapping');
Route::view('/admin/carabao-inventory', 'carabao-inventory')->name('admin.carabao-inventory');
Route::view('/admin/semen-bulls', 'semen-bull')->name('admin.semen-bulls');
Route::get('/fhpr', [ModuleController::class, 'index'])->defaults('module', 'fhpr')->name('ui.fhpr');
Route::get('/bcs', [ModuleController::class, 'index'])->defaults('module', 'bcs')->name('ui.bcs');
Route::get('/milk', [ModuleController::class, 'index'])->defaults('module', 'milk')->name('ui.milk');
Route::get('/mtd', [ModuleController::class, 'index'])->defaults('module', 'mtd')->name('ui.mtd');
Route::get('/health', [ModuleController::class, 'index'])->defaults('module', 'health')->name('ui.health');
Route::get('/monitoring', [ModuleController::class, 'monitoring'])->name('ui.monitoring');
Route::get('/monitoring/cooperatives/{cooperative}/animals', [MonitoringReportController::class, 'animals'])->whereNumber('cooperative')->name('ui.monitoring.animals');
Route::get('/monitoring/bcs-scores', [MonitoringReportController::class, 'bcsScores'])->name('ui.monitoring.bcs-scores');
Route::get('/monitoring/semen-bulls', fn () => DB::table('semen_bulls')->whereRaw('LOWER(status) = ?', ['active'])->orderBy('bull_name')->get(['id','bull_id','bull_name','breed','status']))->name('ui.monitoring.semen-bulls');
Route::post('/monitoring/reports', [MonitoringReportController::class, 'store'])->name('ui.monitoring.reports.store');
Route::get('/monitoring/reports/{report}/data', [MonitoringReportController::class, 'editData'])->whereNumber('report')->name('ui.monitoring.reports.data');
Route::get('/monitoring/reports/{report}/export', [MonitoringReportController::class, 'export'])->whereNumber('report')->name('ui.monitoring.reports.export');
Route::get('/monitoring/reports/{report}', [MonitoringReportController::class, 'show'])->whereNumber('report')->name('ui.monitoring.reports.show');
Route::put('/monitoring/reports/{report}', [MonitoringReportController::class, 'update'])->whereNumber('report')->name('ui.monitoring.reports.update');
Route::get('/exits', [ModuleController::class, 'natureOfExit'])->name('ui.exits');
Route::get('/meat', [ModuleController::class, 'index'])->defaults('module', 'meat')->name('ui.meat');
Route::get('/manure', [ModuleController::class, 'index'])->defaults('module', 'manure')->name('ui.manure');

// Names used by the copied Blade screens; API integration is being retained separately.
Route::get('/admin/carabaos', fn () => DB::table('carabaos')->orderByDesc('id')->get())->name('admin.carabaos.index');
Route::get('/admin/carabaos/options', fn () => response()->json([
    'cooperatives' => DB::table('cooperatives')->orderBy('cooperative_name')->get(),
    'farmers' => DB::table('farmers')->orderBy('last_name')->get(),
]))->name('admin.carabaos.options');
Route::get('/admin/carabao-inventory-api', fn () => DB::table('carabaos')
    ->leftJoin('fhpr_records as fhpr', 'fhpr.carabao_id', '=', 'carabaos.id')
    ->select('carabaos.*', 'fhpr.days_in_milk')
    ->orderByDesc('carabaos.id')->get());
Route::get('/admin/calf-drop-api', function () {
    return DB::table('calves as calf')
        ->join('carabaos as dam', 'dam.id', '=', 'calf.dam_id')
        ->leftJoin('farmers as farmer', 'farmer.id', '=', 'dam.farmer_id')
        ->leftJoin('cooperatives as cooperative', 'cooperative.id', '=', DB::raw('COALESCE(dam.cooperative_id, farmer.cooperative_id)'))
        ->orderByDesc('calf.birth_date')->orderByDesc('calf.id')
        ->get([
            'calf.id', 'calf.calf_animal_id', 'calf.sire_id', 'calf.birth_date', 'calf.sex', 'calf.birth_weight',
            'calf.monitoring_date', 'calf.birth_outcome', 'calf.animal_status', 'calf.calf_findings', 'calf.remarks',
            'dam.animal_id as dam_animal_id', 'dam.animal_name as dam_name', 'dam.breed as dam_breed',
            'cooperative.cooperative_name', 'cooperative.province', 'cooperative.municipality', 'cooperative.barangay',
            'farmer.first_name', 'farmer.middle_name', 'farmer.last_name',
        ])
        ->map(fn ($row) => [
            'id' => $row->id, 'calf_id' => $row->calf_animal_id, 'mother_id' => $row->dam_animal_id,
            'farmer' => trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name]))),
            'province' => $row->province, 'cooperative' => $row->cooperative_name, 'date_of_birth' => $row->birth_date,
            'sex' => $row->sex, 'breed' => $row->dam_breed, 'sire_id' => $row->sire_id,
            'monitored_date' => $row->monitoring_date, 'reported_year' => $row->birth_date ? substr($row->birth_date, 0, 4) : '',
            'birth_outcome' => $row->birth_outcome, 'animal_status' => $row->animal_status,
            'remarks' => trim(implode(' · ', array_filter([$row->calf_findings, $row->remarks]))),
        ]);
})->name('admin.calf-drop.records');
Route::post('/admin/calf-drop-api', function (Request $request) {
    $data = $request->validate([
        'calf_id' => ['nullable', 'string', 'max:255'],
        'mother_id' => ['required', 'string', 'max:255'],
        'date_of_birth' => ['required', 'date'],
        'sex' => ['required', 'in:Male,Female'],
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
    $mother = DB::table('carabaos')
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

    abort_if(! $mother, 422, 'Mother ID was not found in Carabao Inventory.');

    $animalId = trim((string) ($data['calf_id'] ?? ''));
    if ($animalId === '') {
        $animalId = 'CALF-' . date('Y', strtotime($data['date_of_birth'])) . '-' . (string) \Illuminate\Support\Str::uuid();
    }
    abort_if(DB::table('carabaos')->where('animal_id', $animalId)->exists(), 422, 'Calf ID already exists in Carabao Inventory.');

    $id = DB::table('carabaos')->insertGetId([
        'animal_id' => $animalId,
        'farmer_id' => $mother->farmer_id,
        'cooperative_id' => $mother->cooperative_id,
        'animal_name' => null,
        'sex' => $data['sex'],
        'breed' => $data['breed'] ?? $mother->breed,
        'classification' => $data['sex'] === 'Male' ? 'Male Calf' : 'Female Calf',
        'birth_date' => $data['date_of_birth'],
        'status' => $data['animal_status'] ?? 'Live',
        'sire_id' => $data['sire_id'] ?? null,
        'dam_id' => $mother->animal_id,
        'dam_name' => $mother->animal_name,
        'birth_place' => $data['birth_place'] ?? null,
        'breeder' => $data['breeder'] ?? null,
        'association_coop_institution' => $data['association_coop_institution'] ?? null,
        'barangay' => $data['barangay'] ?? $mother->barangay,
        'municipality' => $data['municipality'] ?? $mother->municipality,
        'province' => $data['province'] ?? $mother->province,
        'district' => $data['district'] ?? $mother->district,
        'region' => $data['region'] ?? $mother->region,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'message' => 'Calf added to Carabao Inventory.',
        'animal' => DB::table('carabaos')->where('id', $id)->first(),
    ], 201);
})->name('admin.calf-drop.store');
Route::patch('/admin/carabao-inventory-api/{id}/classification', function (\Illuminate\Http\Request $request, int $id) {
    $data = $request->validate(['classification' => ['nullable', 'string', 'max:255']]);
    $updated = DB::table('carabaos')->where('id', $id)->update([
        'classification' => $data['classification'] ?? null,
        'updated_at' => now(),
    ]);
    abort_if($updated === 0 && ! DB::table('carabaos')->where('id', $id)->exists(), 404, 'Carabao record not found.');
    return response()->json(['message' => 'Classification updated.']);
})->name('admin.carabaos.classification');
Route::get('/admin/semen-bulls-api', fn () => DB::table('semen_bulls')->orderBy('bull_name')->get());
Route::get('/admin/health-nutrition-api', function () {
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
Route::get('/admin/health-records/{record}', [ModuleController::class, 'healthRecord'])->whereNumber('record')->name('admin.health-records.show');
Route::post('/admin/health-records', [ModuleController::class, 'storeHealthRecord'])->name('admin.health-records.store');
Route::put('/admin/health-records/{record}', [ModuleController::class, 'updateHealthRecord'])->whereNumber('record')->name('admin.health-records.update');
Route::delete('/admin/health-records/{record}', [ModuleController::class, 'deleteHealthRecord'])->whereNumber('record')->name('admin.health-records.delete');
Route::post('/admin/carabaos', fn () => response()->json(['message' => 'Carabao API is not connected yet.'], 501))->name('admin.carabaos.store');
Route::post('/admin/carabaos/bulk', fn () => response()->json(['message' => 'Carabao API is not connected yet.'], 501))->name('admin.carabaos.bulk');
Route::post('/admin/carabaos/import', fn () => response()->json(['message' => 'Use the cooperative workbook import.'], 501))->name('admin.carabaos.import');
Route::get('/admin/cooperatives/data', fn () => DB::table('cooperatives')->orderBy('cooperative_name')->get())->name('admin.cooperatives.data');
Route::post('/admin/cooperatives', fn () => response()->json(['message' => 'Cooperative API is not connected yet.'], 501))->name('admin.cooperatives.store');
Route::put('/cooperatives/{id}', function (Request $request, $id) {
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
Route::get('/admin/farmers/data', fn () => DB::table('farmers')->orderBy('last_name')->get()->map(function ($farmer) {
    $farmer->cooperative_record = $farmer->cooperative_id ? DB::table('cooperatives')->find($farmer->cooperative_id) : null;
    return $farmer;
}))->name('admin.farmers.data');
Route::post('/admin/farmers', fn () => response()->json(['message' => 'Farmer API is not connected yet.'], 501))->name('admin.farmers.store');
Route::post('/admin/farmers/bulk', fn () => response()->json(['message' => 'Farmer API is not connected yet.'], 501))->name('admin.farmers.bulk');
Route::post('/admin/farmers/import', [FarmerSpreadsheetController::class, 'import'])->name('admin.farmers.import');
Route::get('/admin/breeding-api/records', fn () => DB::table('mating_records as mr')
    ->leftJoin('carabaos as a', 'a.id', '=', 'mr.carabao_id')
    ->leftJoin('farmers as f', 'f.id', '=', 'a.farmer_id')
    ->leftJoin('cooperatives as c', 'c.id', '=', DB::raw('COALESCE(mr.cooperative_id, a.cooperative_id, f.cooperative_id)'))
    ->leftJoin('semen_bulls as sb', 'sb.id', '=', 'mr.semen_bull_id')
    ->select('mr.*', 'c.cooperative_name', 'a.animal_name', 'a.animal_id as animal_registry_id', 'f.id as farmer_id', 'f.first_name as farmer_first_name', 'f.middle_name as farmer_middle_name', 'f.last_name as farmer_last_name', 'sb.bull_name as matched_bull_name')
    ->orderByDesc('mr.id')->get()->map(function ($record) {
        $source = json_decode((string) $record->source_data, true) ?: [];
        $fields = $source['mating_api_fields'] ?? $source['monitoring_fields'] ?? [];
        $record->straws_used = $fields['straws_used'] ?? $source['semen_used'] ?? null;
        $record->bull_name = $fields['bull_name'] ?? $record->matched_bull_name ?? null;
        $record->estrus_type = $record->estrus_type ?? $fields['estrus_type'] ?? null;
        $record->pregnancy_months = $fields['pregnancy_months'] ?? $source['pregnancy_months'] ?? null;
        $record->pd_technician = $fields['pd_technician'] ?? null;
        $record->record_date = $source['record_date'] ?? $source['monitoring_date'] ?? null;
        $record->pregnancy_status = $source['pregnancy_status'] ?? null;
        $record->farmer_id = $record->farmer_id ?? $fields['farmer_id'] ?? null;
        $record->animal_id = $record->animal_registry_id ?: $record->registry_number;
        return $record;
    }))->name('admin.breeding-api.records');
Route::get('/admin/breeding-api/cooperatives', fn () => DB::table('cooperatives')->orderBy('cooperative_name')->get())->name('admin.breeding-api.cooperatives');
Route::get('/admin/breeding-api/farmers', fn () => DB::table('farmers')->orderBy('last_name')->get())->name('admin.breeding-api.farmers');
Route::get('/admin/breeding-api/carabaos', fn () => DB::table('carabaos')->orderBy('animal_name')->get())->name('admin.breeding-api.carabaos');
Route::get('/admin/breeding-api/semen-bulls', fn () => DB::table('semen_bulls')->orderBy('bull_name')->get())->name('admin.breeding-api.semen-bulls');
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
        'bull_id' => $data['bull_id'] ?: ($bull->bull_id ?? null), 'semen_bull_id' => $bull->id ?? null,
        'technician' => $data['mating_technician'] ?? null, 'pregnancy_check_date' => $data['pd_date'] ?? null,
        'pregnancy_result' => $data['pd_result'] ?? 'Pending', 'expected_calving_date' => $data['expected_calving_date'] ?? null,
        'actual_calving_date' => $data['actual_calving_date'] ?? null, 'remarks' => $data['remarks'] ?? null,
        'source_data' => json_encode($source, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
    ];
    if ($existing) {
        DB::table('mating_records')->where('id', $existing->id)->update($fields);
        return (int) $existing->id;
    }
    return (int) DB::table('mating_records')->insertGetId($fields + ['created_at' => now()]);
};
Route::post('/admin/breeding-api/records', function (Request $request) use ($matingApiRules, $persistMatingRecord) {
    $data = $request->validate($matingApiRules);
    $id = DB::transaction(fn () => $persistMatingRecord($data));
    return response()->json(['message' => 'Mating record saved.', 'id' => $id], 201);
})->name('admin.breeding-api.store');
Route::post('/admin/breeding-api/bulk-store', function (Request $request) use ($matingApiRules, $persistMatingRecord) {
    $rules = ['cooperative_id' => $matingApiRules['cooperative_id'], 'records' => ['required', 'array', 'min:1', 'max:1000']];
    foreach ($matingApiRules as $field => $fieldRules) if ($field !== 'cooperative_id') $rules['records.*.'.$field] = $fieldRules;
    $data = $request->validate($rules);
    $ids = DB::transaction(function () use ($data, $persistMatingRecord) {
        return collect($data['records'])->map(function ($record) use ($data, $persistMatingRecord) {
            $record['cooperative_id'] = $data['cooperative_id'];
            return $persistMatingRecord($record);
        })->all();
    });
    return response()->json(['message' => 'Mating records saved.', 'ids' => $ids], 201);
})->name('admin.breeding-api.bulk');
Route::put('/admin/breeding-api/records/{id}', function (Request $request, int $id) use ($matingApiRules, $persistMatingRecord) {
    $data = $request->validate($matingApiRules);
    $persistMatingRecord($data, $id);
    return response()->json(['message' => 'Mating record updated.', 'id' => $id]);
})->whereNumber('id')->name('admin.breeding-api.update');
Route::delete('/admin/breeding-api/records/{id}', function (int $id) {
    abort_unless(DB::table('mating_records')->where('id', $id)->exists(), 404, 'Mating record not found.');
    DB::table('mating_records')->where('id', $id)->delete();
    return response()->json(['message' => 'Mating record deleted.']);
})->whereNumber('id')->name('admin.breeding-api.delete');

// CBED Modules - temporary access without login
Route::get('/modules/{module}', [ModuleController::class, 'index'])->name('modules.index');
Route::post('/modules/{module}/import', [ModuleController::class, 'import'])->name('modules.import');
Route::get('/modules/{module}/template', [ModuleController::class, 'template'])->name('modules.template');
Route::get('/modules/{module}/export', [ModuleController::class, 'export'])->name('modules.export');
