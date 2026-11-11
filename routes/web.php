
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\CooperativeImportController;
use App\Http\Controllers\CooperativeSpreadsheetController;
use App\Http\Controllers\FarmerSpreadsheetController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

// Login routes
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard - temporary access without login
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.page');
Route::get('/imports/cooperative', [CooperativeImportController::class, 'create'])->name('imports.cooperative.create');
Route::post('/imports/cooperative/preview', [CooperativeImportController::class, 'preview'])->name('imports.cooperative.preview');
Route::post('/imports/cooperative/commit', [CooperativeImportController::class, 'import'])->name('imports.cooperative.import');
Route::get('/admin/cooperatives/export', [CooperativeSpreadsheetController::class, 'export'])->name('admin.cooperatives.export');
Route::post('/admin/cooperatives/import', [CooperativeSpreadsheetController::class, 'import'])->name('admin.cooperatives.import');

// Original CBED_NEW Blade screens
Route::view('/farmers', 'farmer')->name('ui.farmers');
Route::view('/cooperatives', 'cooperative')->name('ui.cooperatives');
Route::view('/carabaos', 'carabao')->name('ui.carabaos');
Route::view('/mating', 'mating')->name('ui.mating');
Route::view('/calf-drop', 'calf-drop')->name('ui.calf-drop');
Route::view('/health-nutrition', 'health-nutrition')->name('ui.health-nutrition');
Route::view('/milk-production', 'milk-production')->name('ui.milk-production');
Route::view('/mil-test', 'mil-test')->name('ui.mil-test');
Route::view('/nature-of-exit', 'nature-of-exit')->name('ui.nature-of-exit');
Route::view('/mapping', 'mapping')->name('ui.mapping');
Route::view('/admin/carabao-inventory', 'carabao-inventory')->name('admin.carabao-inventory');
Route::view('/admin/semen-bulls', 'semen-bull')->name('admin.semen-bulls');
Route::get('/fhpr', [ModuleController::class, 'index'])->defaults('module', 'fhpr')->name('ui.fhpr');
Route::get('/calves', [ModuleController::class, 'index'])->defaults('module', 'calves')->name('ui.calves');
Route::get('/milk', [ModuleController::class, 'index'])->defaults('module', 'milk')->name('ui.milk');
Route::get('/mtd', [ModuleController::class, 'index'])->defaults('module', 'mtd')->name('ui.mtd');
Route::get('/health', [ModuleController::class, 'index'])->defaults('module', 'health')->name('ui.health');
Route::get('/exits', [ModuleController::class, 'index'])->defaults('module', 'exits')->name('ui.exits');
Route::get('/semen-used', [ModuleController::class, 'index'])->defaults('module', 'semen-used')->name('ui.semen-used');
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
