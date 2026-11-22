<?php

// Run with: php tests/account-access.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$app->instance('env', 'testing');
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
config([
    'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'session.driver' => 'array',
    'cache.default' => 'array',
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
]);

foreach ([
    '0001_01_01_000000_create_users_table',
    '2026_10_30_000001_create_cbed_tables',
    '2026_11_03_000002_extend_cbed_tracking',
    '2026_11_03_000003_create_mtd_records_table',
    '2026_11_03_000003_add_parent_ids_to_carabaos',
    '2026_11_04_000002_scope_archive_records_to_cooperative',
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

$cookies = [];
function visit(string $method, string $path, int $status, array $data = [])
{
    global $kernel, $cookies;

    Auth::forgetGuards();
    $request = Request::create($path, $method, $data, $cookies, [], ['HTTP_REFERER' => 'http://localhost'.$path]);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    if ($response->getStatusCode() !== $status) {
        throw new RuntimeException("$method $path expected HTTP $status, got {$response->getStatusCode()}.");
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

visit('GET', '/login', 200);
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
$cooperative = DB::table('cooperatives')->insertGetId(['cooperative_code' => 'TEST', 'cooperative_name' => 'Test Cooperative']);
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

echo "Account access and activity log checks passed.\n";
