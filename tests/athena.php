<?php

// Run: php tests/athena.php (isolated fixtures); php tests/athena.php --live (read-only local database comparison).
require dirname(__DIR__).'/vendor/autoload.php';

use App\Http\Controllers\AthenaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Models\User;
use App\Support\AthenaData;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$app->instance('request', Request::create('/'));
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL.$error->getTraceAsString().PHP_EOL);
    exit(1);
});

function same($expected, $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description.' expected '.json_encode($expected).', got '.json_encode($actual));
    }
}

function chatRequest(string $question = 'How many lactating and pregnant carabaos?'): Request
{
    return Request::create('/athena/chat', 'POST', ['messages' => [['role' => 'user', 'content' => $question]]], [], [], ['HTTP_ACCEPT' => 'application/json']);
}

$data = new AthenaData;
$admin = new User(['role' => 'admin']);
$admin->id = 101;
$run = fn ($module, $filters = [], $page = 1) => $data->run($admin, compact('module', 'filters'), $page);

if (in_array('--live', $argv, true)) {
    $dashboard = $app->make(DashboardController::class)->index(Request::create('/dashboard'))->getData();
    $totals = $run('animals')['totals'];
    same($dashboard['animalSummary']['breedable'], $totals['dashboard_breedable'], 'Breedable matches dashboard');
    $public = $data->publicDashboard();
    same($dashboard['animalSummary']['breedable'], $public['totals']['breedable'], 'Public breedable matches dashboard');
    same([], array_diff(array_keys($public['totals']), ['animals', 'live', 'dead', 'breedable', 'pregnant', 'lactating', 'dry']), 'Public data limits');
    $year = $dashboard['milkYear'];
    $dates = ['date_from' => "$year-01-01", 'date_to' => "$year-12-31"];
    same((float) $dashboard['milkTotal'], $run('milk', $dates)['totals']['liters'], 'Milk matches production totals');
    $monitoring = $app->make(ModuleController::class)->monitoring(Request::create('/monitoring', 'GET', ['year' => $year]))->getData();
    same($monitoring['consolidatedRowsPage']->total(), $run('monitoring_population', ['year' => $year])['totals']['live_animals'], 'Monitoring matches consolidated table');
    foreach (['breeding', 'calves', 'health', 'milk', 'mtd', 'monitoring', 'bcs', 'exits', 'semen_bulls', 'semen_used', 'fhpr', 'products', 'farmers', 'cooperatives'] as $module) {
        $result = $run($module, ['list' => true]);
        same($result['totals']['records'], $result['page']['total'], "$module pagination total");
        same(min(AthenaData::PAGE_SIZE, $result['page']['total']), count($result['page']['rows']), "$module page size");
    }
    echo 'Live comparisons passed. ', json_encode(array_intersect_key($totals, array_flip(['lactating', 'confirmed_pregnant', 'lactating_and_confirmed_pregnant', 'dashboard_breedable']))), PHP_EOL;
    exit;
}

$app->instance('env', 'testing');
config([
    'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'session.driver' => 'array', 'cache.default' => 'array',
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'services.athena.base_url' => 'https://athena.example/v1', 'services.athena.api_key' => 'test', 'services.athena.model' => 'test',
]);

foreach ([
    'CREATE TABLE carabaos (id INTEGER PRIMARY KEY, animal_id TEXT, cooperative_id INTEGER, farmer_id INTEGER, registry_number TEXT, classification TEXT, status TEXT, sex TEXT, breed TEXT, birth_date TEXT, death_date TEXT, lactation_status TEXT, nature_of_exit TEXT, exit_reason TEXT)',
    'CREATE TABLE farmers (id INTEGER PRIMARY KEY, cooperative_id INTEGER, farmer_code TEXT, first_name TEXT, last_name TEXT)',
    'CREATE TABLE cooperatives (id INTEGER PRIMARY KEY, cooperative_name TEXT, cooperative_code TEXT)',
    'CREATE TABLE mating_records (id INTEGER PRIMARY KEY, carabao_id INTEGER, cooperative_id INTEGER, mating_date TEXT, mating_type TEXT, pregnancy_check_date TEXT, pregnancy_result TEXT, expected_calving_date TEXT, actual_calving_date TEXT)',
    'CREATE TABLE calves (id INTEGER PRIMARY KEY, dam_id INTEGER, birth_date TEXT, birth_outcome TEXT)',
    'CREATE TABLE milk_records (id INTEGER PRIMARY KEY, carabao_id INTEGER, farmer_id INTEGER, cooperative_id INTEGER, record_date TEXT, total_liters DECIMAL)',
    'CREATE TABLE monitoring_reports (id INTEGER PRIMARY KEY, cooperative_id INTEGER, report_date TEXT)',
    'CREATE TABLE monitoring_report_details (id INTEGER PRIMARY KEY, monitoring_report_id INTEGER, carabao_id INTEGER)',
    "INSERT INTO cooperatives VALUES (1, 'Bataan Farmers', 'BTF'), (2, 'Another Cooperative', 'AC')",
    "INSERT INTO farmers VALUES (1, 1, 'F1', 'Example', 'Farmer')",
    "INSERT INTO carabaos VALUES
        (1, 'TEST-1', 1, 1, 'REG1', 'CowLactating', 'Live', 'Female', 'Murrah', '2021-01-01', NULL, NULL, NULL, NULL),
        (2, 'TEST-2', 1, 1, 'REG2', 'HeiferPregnant', 'Live', 'F', 'Murrah', '2022-01-01', NULL, NULL, NULL, NULL),
        (3, 'TEST-3', 2, NULL, 'REG3', 'CowDry', 'Live', 'F', 'Murrah', '2022-01-01', NULL, NULL, NULL, NULL),
        (4, 'TEST-4', 1, 1, 'REG4', 'CowLactatingPregnant', 'Dead', 'F', 'Murrah', '2021-01-01', '2026-05-01', NULL, NULL, NULL),
        (5, 'TEST-5', 1, 1, 'REG5', 'CowDryPregnant', 'Live', 'F', 'Murrah', '2022-01-01', NULL, NULL, NULL, NULL),
        (6, 'TEST-6', 1, 1, 'REG6', 'CowLactatingPregnant', 'Out', 'F', 'Murrah', '2021-01-01', NULL, NULL, 'Transferred', NULL)",
    "INSERT INTO mating_records VALUES
        (1, 1, 1, '2025-11-01', 'AI', '2026-01-02', 'Positive', '2026-07-01', NULL),
        (2, 1, 1, '2025-11-01', 'AI', '2026-01-08', '+', '2026-07-01', NULL),
        (3, 2, 1, '2026-01-02', 'AI', NULL, 'Pending', NULL, NULL),
        (4, 3, 2, '2025-11-02', 'N', '2026-01-10', 'Positive', NULL, NULL),
        (5, 4, 1, '2026-01-02', 'AI', '2026-03-02', 'Positive', NULL, NULL),
        (6, 5, 1, '2025-11-01', 'AI', '2026-01-10', 'Positive', NULL, NULL),
        (7, 6, 1, '2025-11-01', 'AI', '2026-01-10', 'Positive', NULL, NULL)",
    "INSERT INTO calves VALUES (1, 5, '2026-03-02', 'Live')",
    "INSERT INTO milk_records VALUES (1, 1, 1, 1, '2026-01-01', 10.5), (2, 3, NULL, 2, '2025-12-31', 20)",
    "INSERT INTO monitoring_reports VALUES (1, 1, '2026-01-01')",
    "INSERT INTO monitoring_report_details VALUES (1, 1, 1), (2, 1, 1)",
] as $statement) {
    DB::statement($statement);
}

\Illuminate\Support\Carbon::setTestNow('2026-11-21 12:00:00');
$totals = $run('animals')['totals'];
same(4, $totals['active'], 'Exclude dead and exited animals');
same(1, $run('animals', ['status' => 'Transferred'])['totals']['matched_animals'], 'Distinguish transferred animals from generic exits');
same(2, $totals['lactating'], 'Lactation uses current classification and subsequent calving');
same(2, $totals['confirmed_pregnant'], 'Use distinct current positive diagnoses only');
same(1, $totals['lactating_and_confirmed_pregnant'], 'Count lactation and pregnancy independently');
same(2, $totals['pregnancy_unknown'], 'Pending and completed pregnancies are unknown');
same(1, $run('animals', ['cooperative' => 'bTf'])['totals']['confirmed_pregnant'], 'Cooperative code filter');
same(1, $run('animals', ['farmer' => 'Example Farmer'])['totals']['confirmed_pregnant'], 'Farmer identity filter');
same('TEST-1', $run('animals', ['reproductive_status' => 'both', 'list' => true])['page']['rows'][0]['Animal ID'], 'Authorized matched animal list');
same(10.5, $run('milk', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31'])['totals']['liters'], 'Milk date filter');
same('TEST-3', $run('milk', ['date_to' => '2025-12-31', 'list' => true])['page']['rows'][0]['animal_id'], 'Dated event record list');
same(1, $run('calves', ['date_from' => '2026-01-01'])['totals']['calf_births'], 'Calf births');
same(1, $run('monitoring')['totals']['unique_linked_animals'], 'Unique monitored animals');
same(0, $run('animals', ['cooperative' => 'No matching cooperative'])['totals']['matched_animals'], 'Empty scope');

same(401, $kernel->handle(Request::create('/athena/records', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']))->getStatusCode(), 'Guest record access denied');
same(401, $kernel->handle(chatRequest())->getStatusCode(), 'Guest chat access denied');
$controller = $app->make(AthenaController::class);
$member = new User(['role' => 'member']);

$request = chatRequest();
$request->setUserResolver(fn () => $admin);
$url = 'https://athena.example/v1/chat/completions';
$reply = fn ($text) => ['choices' => [['message' => ['content' => $text]]]];
$databasePlan = '{"queries":[{"module":"animals","filters":{"reproductive_status":"both","list":true}}]}';
$app->instance(AthenaData::class, new class extends AthenaData {
    public function publicDashboard(): array
    {
        return ['source' => 'Dashboard', 'totals' => ['breedable' => 5], 'note' => 'Public dashboard totals.'];
    }
});
Http::preventStrayRequests();
Http::fake([$url => Http::sequence()
    ->push($reply('{"queries":[]}'))
    ->push($reply('A carabao is a water buffalo.'))
    ->push($reply('{"queries":[{"module":"animals","filters":{}}]}'))
    ->push($reply($databasePlan))
    ->push($reply('I don’t have a verified figure for that group.'))
    ->push($reply($databasePlan))
    ->push($reply('I don’t have a verified figure for that group.'))
    ->push($reply($databasePlan))
    ->push($reply('One is both lactating and confirmed pregnant.'))
    ->push($reply('{"queries":[{"module":"animals","filters":{"sql":"SELECT * FROM users"}}]}'))
    ->pushStatus(503)]);
$guestReply = $controller->chat(chatRequest('What is a carabao?'), $app->make(AthenaData::class));
same(200, $guestReply->getStatusCode(), 'Guest general chat works');
same([], $guestReply->getData(true)['data'], 'Guest receives no CBED records');
$publicReply = $controller->chat(chatRequest('How many breedable animals?'), $app->make(AthenaData::class));
same(200, $publicReply->getStatusCode(), 'Guest dashboard query succeeds');
same(['breedable' => 5], $publicReply->getData(true)['data'][0]['totals'], 'Guest receives public dashboard totals only');
same(true, str_contains(Http::recorded()[2][0]->data()['messages'][0]['content'], 'dashboard module with empty filters'), 'Guest router is instructed to use public dashboard for simple counts');
$guestDetailed = $controller->chat(chatRequest(), $app->make(AthenaData::class));
same(200, $guestDetailed->getStatusCode(), 'Guest receives a chat answer without a sign-in error');
same([], $guestDetailed->getData(true)['data'], 'Guest receives no detailed data');
$memberRequest = chatRequest();
$memberRequest->setUserResolver(fn () => $member);
same([], $controller->chat($memberRequest, $data)->getData(true)['data'], 'Member receives no detailed data');
$result = $controller->chat($request, $data);
same(200, $result->getStatusCode(), 'Successful report');
$page = $result->getData(true)['data'][0]['page'];
same(1, $page['total'], 'Report includes matching records');
$recordRequest = Request::create('/athena/records', 'POST', ['token' => $page['token'], 'page' => 2]);
$recordRequest->setUserResolver(fn () => $admin);
same([], $controller->records($recordRequest, $data)->getData(true)['page']['rows'], 'Page bounds');
$otherAdmin = clone $admin;
$otherAdmin->id = 102;
$recordRequest->setUserResolver(fn () => $otherAdmin);
try {
    $controller->records($recordRequest, $data);
    throw new RuntimeException('Missing pagination ownership check');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
    same(403, $error->getStatusCode(), 'Pagination is bound to the original user');
}
same(422, $controller->chat($request, $data)->getStatusCode(), 'Model cannot submit arbitrary filters or SQL');
same(502, $controller->chat($request, $data)->getStatusCode(), 'Provider HTTP failure is handled');
same(false, str_contains(json_encode(Http::recorded()[8][0]->data()), 'TEST-1'), 'Animal-level rows stay out of the provider request');
echo "Athena query and request tests passed.\n";
