
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
    ->leftJoin('cooperatives as c', 'c.id', '=', DB::raw('COALESCE(mr.cooperative_id, a.cooperative_id)'))
    ->leftJoin('semen_bulls as sb', 'sb.id', '=', 'mr.semen_bull_id')
    ->select('mr.*', 'c.cooperative_name', 'a.animal_name', 'f.first_name as farmer_first_name', 'f.middle_name as farmer_middle_name', 'f.last_name as farmer_last_name', 'sb.bull_name as matched_bull_name')
    ->orderByDesc('mr.id')->get())->name('admin.breeding-api.records');
Route::get('/admin/breeding-api/cooperatives', fn () => DB::table('cooperatives')->orderBy('cooperative_name')->get())->name('admin.breeding-api.cooperatives');
Route::get('/admin/breeding-api/farmers', fn () => DB::table('farmers')->orderBy('last_name')->get())->name('admin.breeding-api.farmers');
Route::get('/admin/breeding-api/carabaos', fn () => DB::table('carabaos')->orderBy('animal_name')->get())->name('admin.breeding-api.carabaos');
Route::get('/admin/breeding-api/semen-bulls', fn () => DB::table('semen_bulls')->orderBy('bull_name')->get())->name('admin.breeding-api.semen-bulls');
Route::post('/admin/breeding-api/records', fn () => response()->json(['message' => 'Breeding API is not connected yet.'], 501))->name('admin.breeding-api.store');
Route::post('/admin/breeding-api/bulk-store', fn () => response()->json(['message' => 'Breeding API is not connected yet.'], 501))->name('admin.breeding-api.bulk');

// CBED Modules - temporary access without login
Route::get('/modules/{module}', [ModuleController::class, 'index'])->name('modules.index');
Route::post('/modules/{module}/import', [ModuleController::class, 'import'])->name('modules.import');
Route::get('/modules/{module}/template', [ModuleController::class, 'template'])->name('modules.template');
Route::get('/modules/{module}/export', [ModuleController::class, 'export'])->name('modules.export');
