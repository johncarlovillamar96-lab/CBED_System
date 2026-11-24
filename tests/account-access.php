<?php

// Run with: php tests/account-access.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

ob_start();
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (ob_get_clean() !== '') {
    fwrite(STDERR, "Application bootstrap emitted bytes before the HTTP response.\n");
    exit(1);
}
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
config([
    'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'session.driver' => 'array',
    'session.domain' => null,
    'session.secure' => null,
    'cache.default' => 'array',
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
]);

foreach ([
    '0001_01_01_000000_create_users_table',
    '2026_10_30_000001_create_cbed_tables',
    '2026_11_03_000002_add_semen_bull_tracking_to_mating_records',
    '2026_11_03_000002_extend_cbed_tracking',
    '2026_11_03_000003_create_mtd_records_table',
    '2026_11_03_000003_add_parent_ids_to_carabaos',
    '2026_11_04_000002_scope_archive_records_to_cooperative',
    '2026_11_04_000006_add_bd_all_details_to_carabaos',
    '2026_11_13_000001_create_bcs_history_and_archive_source_data',
    '2026_11_15_000001_create_monitoring_reports_table',
    '2026_11_16_000001_add_animal_monitoring_details',
    '2026_11_17_000001_link_monitoring_reports_to_bcs_records',
    '2026_11_19_000001_add_inventory_disposal_details',
    '2026_11_22_000001_add_name_parts_to_users',
    '2026_11_22_000002_create_activity_logs_table',
] as $migration) {
    (require dirname(__DIR__)."/database/migrations/{$migration}.php")->up();
}
DB::connection()->getPdo()->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match('/'.$pattern.'/', $value), 2);
// The archive migration contains MySQL ALTER TABLE statements.
Schema::create('fhpr_records', function (Blueprint $table) {
    $table->foreignId('carabao_id')->constrained();
    $table->integer('days_in_milk')->nullable();
});
Schema::table('mtd_records', fn (Blueprint $table) => $table->date('dryoff_date')->nullable());
foreach (['mating_records', 'health_records'] as $table) {
    Schema::table($table, fn (Blueprint $schema) => $schema->string('registry_number')->nullable());
}

$cookies = [];
$forwarded = in_array('--forwarded-https', $argv, true);
$origin = $forwarded ? 'https://cbed-test.example' : 'http://localhost';
function visit(string $method, string $path, int $status, array $data = [])
{
    global $kernel, $cookies, $forwarded, $origin, $app;

    Auth::forgetGuards();
    if ($method !== 'GET' && $status !== 419) {
        $data['_token'] = $app['session.store']->token();
    }
    $server = ['HTTP_REFERER' => $origin.$path];
    if ($forwarded) {
        $server += [
            'HTTP_X_FORWARDED_HOST' => 'cbed-test.example',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];
    }
    $request = Request::create($path, $method, $data, $cookies, [], $server);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->isSecure() !== $forwarded || $cookie->getDomain() !== null) {
            throw new RuntimeException("Incorrect cookie scope for $method $path.");
        }
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    if ($response->getStatusCode() !== $status) {
        throw new RuntimeException("$method $path expected HTTP $status, got {$response->getStatusCode()}.");
    }
    $location = $response->headers->get('Location');
    $relative = $location !== null && str_starts_with($location, '/') && ! str_starts_with($location, '//');
    if ($location !== null && ! $relative && $location !== $origin && ! str_starts_with($location, $origin.'/')) {
        throw new RuntimeException("Incorrect redirect for $method $path: $location.");
    }

    return $response;
}

$password = 'Test123!';
$admin = User::create(['name' => 'Test Admin', 'email' => 'owner@example.com', 'role' => User::ADMIN, 'password' => $password]);
User::create(['name' => 'Legacy Admin', 'email' => 'admin@cbed.local', 'role' => User::ADMIN, 'password' => 'ChangeMe123!']);
$report = DB::table('monitoring_reports')->insertGetId([
    'cooperative_name' => 'Test Cooperative', 'report_date' => '2026-11-22', 'report_data' => '{"rows":[]}',
]);
DB::table('monitoring_report_details')->insert(['monitoring_report_id' => $report]);

$login = visit('GET', '/login', 200)->getContent();
foreach (['/images/login-carabao.jpg', '/images/da-pcc-nias-login.png', '/js/password-visibility.js'] as $asset) {
    if (! str_contains($login, $asset) || ! is_file(public_path(ltrim($asset, '/')))) {
        throw new RuntimeException("Login asset is missing: $asset.");
    }
}
visit('POST', '/login', 419);
visit('GET', '/signup', 302);
visit('POST', '/signup', 302);
visit('GET', '/monitoring', 302);
visit('POST', '/login', 302, ['email' => 'admin@cbed.local', 'password' => 'ChangeMe123!']);
visit('GET', '/login', 200); // The legacy seed password leaves the session signed out.

visit('POST', '/login', 302, ['email' => $admin->email, 'password' => $password]);
$accountsPage = visit('GET', '/signup', 200)->getContent();
if (! str_contains($accountsPage, '<dialog id="account-signup"') || ! str_contains($accountsPage, 'owner@example.com')) {
    throw new RuntimeException('The accounts page should list existing users and provide a signup modal.');
}
foreach (User::ROLES as $role => $label) {
    visit('POST', '/signup', 302, [
        'first_name' => 'Test', 'last_name' => $label, 'email' => "$role@example.com", 'role' => $role,
        'password' => $password, 'password_confirmation' => $password,
    ]);
    if (User::where('email', "$role@example.com")->value('role') !== $role) {
        throw new RuntimeException("Signup did not save the $role account.");
    }
}
visit('POST', '/signup', 302, [
    'first_name' => 'Short', 'last_name' => 'Password', 'email' => 'short@example.com', 'role' => User::NON_ADMIN,
    'password' => 'Test12!', 'password_confirmation' => 'Test12!',
]);
if (User::where('email', 'short@example.com')->exists()) {
    throw new RuntimeException('Signup accepted a seven-character password.');
}
$accountsPage = visit('GET', '/signup', 200)->getContent();
if (! str_contains($accountsPage, 'technician@example.com') || ! str_contains($accountsPage, 'Test Technician')) {
    throw new RuntimeException('The accounts table should show newly created accounts with their full names.');
}
visit('POST', '/signup', 302, [
    'first_name' => 'Duplicate', 'last_name' => 'Account', 'email' => 'technician@example.com', 'role' => User::TECHNICIAN,
    'password' => $password, 'password_confirmation' => $password,
]);
$invalidSignupPage = visit('GET', '/signup', 200)->getContent();
if (! str_contains($invalidSignupPage, 'The email has already been taken.') || ! preg_match('/<dialog[^>]+\bdata-reopen\b/', $invalidSignupPage)) {
    throw new RuntimeException('Validation errors should be shown in the reopened signup modal.');
}
$managed = User::where('email', 'admin@example.com')->firstOrFail();
$managedPath = "/signup/{$managed->id}";
foreach (['view', 'edit', 'delete'] as $action) {
    $modal = visit('GET', "/signup?action=$action&account={$managed->id}", 200)->getContent();
    if (! str_contains($modal, ucfirst($action).' Account')) {
        throw new RuntimeException("Missing the $action account dialog.");
    }
}
$edited = ['first_name' => 'Mary Jane', 'last_name' => 'De la Cruz', 'email' => 'edited@example.com', 'role' => User::NON_ADMIN];
visit('PUT', $managedPath, 302, $edited);
$managed->refresh();
if ($managed->name !== 'Mary Jane De la Cruz' || $managed->last_name !== 'De la Cruz' || $managed->role !== User::NON_ADMIN) {
    throw new RuntimeException('Account edit should preserve both name parts and update the role.');
}
visit('PUT', "/signup/{$admin->id}", 403, array_replace($edited, ['email' => $admin->email]));
visit('DELETE', "/signup/{$admin->id}", 403);
visit('DELETE', $managedPath, 302);
if ($managed->fresh()) {
    throw new RuntimeException('The deleted account still exists.');
}
visit('POST', '/logout', 302);
visit('GET', '/signup', 302);

$response = visit('POST', '/login', 302, ['email' => 'technician@example.com', 'password' => $password]);
if (! str_ends_with($response->headers->get('Location'), '/monitoring')) {
    throw new RuntimeException('Technician should land in Monitoring.');
}
visit('GET', '/monitoring', 200);
visit('GET', '/activity-logs', 200);
if (! str_contains(visit('GET', "/monitoring/reports/$report", 200)->getContent(), 'Update Report')) {
    throw new RuntimeException('Technician should see the report update action.');
}
visit('GET', "/monitoring/reports/$report/data", 200);
visit('GET', '/farmers', 403);
visit('GET', '/admin/farmers/data', 403);
$cooperative = DB::table('cooperatives')->insertGetId([
    'cooperative_code' => 'TEST', 'cooperative_name' => 'Test Cooperative',
    'barangay' => 'Test Barangay', 'municipality' => 'Test Municipality', 'province' => 'Test Province',
]);
$animal = DB::table('carabaos')->insertGetId(['animal_id' => 'TEST-01', 'cooperative_id' => $cooperative, 'sex' => 'Female']);
$reportInput = [
    'cooperative_id' => $cooperative, 'monitoring_date' => '2026-11-22',
    'rows' => [['carabao_id' => $animal, 'availability_status' => 'unavailable', 'unavailability_reason' => 'Not Present']],
];
visit('POST', '/monitoring/reports', 302, $reportInput);
$savedReport = DB::table('monitoring_reports')->where('cooperative_id', $cooperative)->value('id');
if (! $savedReport) {
    throw new RuntimeException('Technician report was not saved.');
}
visit('PUT', "/monitoring/reports/$savedReport", 302, ['remarks' => 'Updated by technician'] + $reportInput);
if (DB::table('monitoring_reports')->where('id', $savedReport)->value('remarks') !== 'Updated by technician') {
    throw new RuntimeException('Technician report update was not saved.');
}
visit('POST', '/admin/farmers', 403);
visit('POST', '/signup', 403);
visit('PUT', "/signup/{$admin->id}", 403, $edited);
visit('DELETE', "/signup/{$admin->id}", 403);
visit('POST', '/logout', 302);

visit('POST', '/login', 302, ['email' => 'non-admin@example.com', 'password' => $password]);
visit('GET', '/farmers', 200);
visit('GET', '/monitoring', 200);
visit('GET', '/activity-logs', 200);
if (str_contains(visit('GET', "/monitoring/reports/$report", 200)->getContent(), 'Update Report')) {
    throw new RuntimeException('Non-admin should see a read-only report.');
}
$download = visit('GET', "/monitoring/reports/$report/export", 200);
if (! str_contains($download->headers->get('Content-Disposition', ''), 'attachment')) {
    throw new RuntimeException('Non-admin report export should be downloadable.');
}
visit('GET', "/monitoring/reports/$report/data", 403);
visit('POST', '/monitoring/reports', 403);
visit('PUT', "/monitoring/reports/$report", 403);
visit('DELETE', '/mtd/1', 403);
visit('GET', '/imports/cooperative-workbook', 403);
visit('GET', '/signup', 403);
visit('POST', '/signup', 403);

$nonAdminPage = visit('GET', '/activity-logs', 200)->getContent();
if (str_contains($nonAdminPage, 'aria-label="System activity"')) {
    throw new RuntimeException('Non-admin should not see the system activity log.');
}
visit('POST', '/logout', 302);
visit('POST', '/login', 302, ['email' => $admin->email, 'password' => $password]);
$auditPage = visit('GET', '/activity-logs?role=non-admin&action=Download', 200)->getContent();
if (! str_contains($auditPage, 'ui.monitoring.reports.export') || ! str_contains($auditPage, 'Test Non-admin')) {
    throw new RuntimeException('The system activity log should show non-admin report downloads.');
}

visit('GET', '/calves', 200);
visit('GET', '/admin/semen-bulls', 200);
foreach (['/health-nutrition' => '/health', '/milk-production' => '/milk', '/mil-test' => '/mtd'] as $legacy => $current) {
    if (visit('GET', $legacy, 302)->headers->get('Location') !== $current) {
        throw new RuntimeException("The legacy path $legacy should open the shared database view.");
    }
}
DB::table('milk_records')->insert(['carabao_id' => $animal, 'record_date' => '2026-11-22', 'total_liters' => 5]);
$inventoryRow = visit('GET', '/admin/carabao-inventory-api', 200)->getData(true)[0];
if ((int) $inventoryRow['has_milk_production'] !== 1) {
    throw new RuntimeException('Inventory should use database milk production.');
}
$calfDrop = ['mother_id' => 'TEST-01', 'date_of_birth' => '2026-11-22', 'birth_outcome' => 'Stillbirth'];
visit('POST', '/admin/calf-drop-api', 201, $calfDrop);
visit('POST', '/admin/calf-drop-api', 422, $calfDrop);
$calfRecord = visit('GET', '/admin/calf-drop-api', 200)->getData(true)[0];
if ($calfRecord['calf_id'] !== null || $calfRecord['animal_status'] !== 'Dead' || DB::table('carabaos')->count() !== 1) {
    throw new RuntimeException('Stillbirth should be shared without creating an inventory animal.');
}

$farmer = DB::table('farmers')->insertGetId(['cooperative_id' => $cooperative, 'first_name' => 'Test', 'last_name' => 'Farmer']);
DB::table('carabaos')->where('id', $animal)->update(['farmer_id' => $farmer]);
$secondAnimal = DB::table('carabaos')->insertGetId([
    'animal_id' => 'TEST-02', 'farmer_id' => $farmer, 'sex' => 'F',
    'birth_date' => '2025-01-01', 'classification' => 'Heifer',
]);
DB::table('carabaos')->insert(['animal_id' => 'TEST-03', 'farmer_id' => $farmer, 'sex' => 'Male']);
$emptyCooperative = DB::table('cooperatives')->insertGetId(['cooperative_code' => 'EMPTY', 'cooperative_name' => 'Empty Cooperative']);
$rosterUrl = "/monitoring/cooperatives/$cooperative/animals";
if (! str_contains(visit('GET', '/monitoring', 200)->getContent(), 'data-animals-url="'.$rosterUrl.'"')) {
    throw new RuntimeException('The cooperative option should contain its same-origin roster URL.');
}
$roster = visit('GET', $rosterUrl, 200)->getData(true);
if (array_column($roster, 'carabao_id') !== [$animal, $secondAnimal]
    || (int) $roster[1]['farmer_id'] !== $farmer || ! $roster[1]['classification']) {
    throw new RuntimeException('The roster should include the farmer’s live females and their classifications.');
}
if (visit('GET', "/monitoring/cooperatives/$emptyCooperative/animals", 200)->getContent() !== '[]') {
    throw new RuntimeException('A cooperative with no eligible animals should return an empty JSON array.');
}
$reportCount = DB::table('monitoring_reports')->count();
visit('POST', '/monitoring/reports', 302, [
    'cooperative_id' => $cooperative, 'monitoring_date' => '2026-11-24',
    'rows' => [
        ['carabao_id' => $animal, 'availability_status' => 'available', 'body_condition_score' => 3.5, 'monitoring_services' => [
            'breeding' => json_encode(['mating_form' => true, 'method' => 'NM', 'breeding_date' => '2026-11-23']),
            'health_nutrition' => json_encode(['services' => [['selected' => true, 'type' => 'Other Services', 'other_service_name' => 'Fixture service']]]),
        ]],
        ['carabao_id' => $secondAnimal, 'availability_status' => 'available', 'remarks' => 'Second animal', 'monitoring_services' => [
            'calf_monitoring' => json_encode([
                'date_of_birth' => '2026-11-24', 'birth_outcome' => 'Live Birth', 'sex' => 'Female', 'calf_id' => 'MONITORING-CALF',
            ]),
        ]],
    ],
]);
$newReport = DB::table('monitoring_reports')->max('id');
if (DB::table('monitoring_reports')->count() !== $reportCount + 1
    || DB::table('monitoring_report_details')->where('monitoring_report_id', $newReport)->count() !== 2
    || ! DB::table('bcs_records')->where('carabao_id', $animal)->where('body_condition_score', 3.5)->exists()
    || ! DB::table('mating_records')->where('carabao_id', $animal)->where('mating_date', '2026-11-23')->exists()
    || ! str_contains((string) DB::table('health_records')->where('carabao_id', $animal)->value('source_data'), 'Fixture service')
    || ! DB::table('calves')->where('dam_id', $secondAnimal)->where('birth_outcome', 'Live Birth')->exists()) {
    throw new RuntimeException('A monitoring report should save distinct animals and sync the selected services.');
}
foreach ([
    [User::ADMIN, 'Sign in', 'login.post', 302],
    [User::ADMIN, 'Create', 'signup.store', 302],
    [User::TECHNICIAN, 'Update', 'ui.monitoring.reports.update', 302],
    [User::TECHNICIAN, 'View', 'ui.farmers', 403],
    [User::NON_ADMIN, 'Download', 'ui.monitoring.reports.export', 200],
    [User::NON_ADMIN, 'Sign out', 'logout', 302],
] as [$role, $action, $route, $status]) {
    if (! DB::table('activity_logs')->where(['actor_role' => $role, 'action' => $action, 'route' => $route, 'status' => $status])->exists()) {
        throw new RuntimeException("Missing activity entry for $role $action $route.");
    }
}

visit('POST', '/admin/calf-drop-api', 201, [
    'mother_id' => 'TEST-01', 'calf_id' => 'DIRECT-CALF', 'date_of_birth' => '2026-11-25', 'sex' => 'Male',
]);
foreach (['MONITORING-CALF' => '2026-11-24', 'DIRECT-CALF' => '2026-11-25'] as $calfId => $birthDate) {
    $calf = DB::table('carabaos')->where('animal_id', $calfId)->first();
    if ($calf?->registry_number !== $calfId || $calf->birth_date !== $birthDate
        || (int) $calf->cooperative_id !== $cooperative || $calf->breeder !== 'Test Cooperative'
        || $calf->association_coop_institution !== 'Test Cooperative'
        || $calf->birth_place !== 'Test Barangay, Test Municipality, Test Province') {
        throw new RuntimeException("Inventory calf $calfId should carry its registry and cooperative birth details.");
    }
}

echo "Account access and activity log checks passed.\n";
