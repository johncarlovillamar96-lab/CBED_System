<?php

// Run with: php tests/remember-me.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Http\Middleware\RecordActivity;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config([
    'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
    'session.driver' => 'array',
    'session.domain' => null,
    'session.secure' => null,
    'cache.default' => 'array',
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
]);

foreach ([
    '0001_01_01_000000_create_users_table',
    '2026_11_22_000001_add_name_parts_to_users',
    '2026_11_27_000001_add_sex_to_users',
] as $migration) {
    (require dirname(__DIR__)."/database/migrations/{$migration}.php")->up();
}

$password = 'TestPassword123!';
$id = DB::table('users')->insertGetId([
    'name' => 'Remember Tester', 'email' => 'remember@example.test', 'role' => User::NON_ADMIN,
    'password' => Hash::make($password), 'remember_token' => 'legacy-token',
]);
(require dirname(__DIR__).'/database/migrations/2026_11_27_000002_add_is_active_to_users.php')->up();
$user = User::findOrFail($id);

// The application activity capture uses MySQL or PostgreSQL session variables.
// Exclude it from these SQLite fixture routes so the remaining web stack is exercised.
foreach (['logout', 'signup.update'] as $name) {
    app('router')->getRoutes()->getByName($name)->withoutMiddleware(RecordActivity::class);
}
Route::middleware(['web', 'auth', 'workspace'])->get('/_remember-me-test', function (Request $request) {
    return response()->json(['id' => $request->user()->id, 'remembered' => Auth::viaRemember()]);
});

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function requestAs(array &$cookies, string $path, string $method = 'GET', array $data = [], string $host = 'local'): Response
{
    global $app, $kernel;

    Auth::forgetGuards();
    $app->forgetInstance('cookie');
    $app['session.store']->flush();
    $origin = $host === 'https' ? 'https://cbed.example.test' : 'http://localhost';
    $server = ['HTTP_REFERER' => $origin.'/login', 'REMOTE_ADDR' => '127.0.0.1'];
    if ($host === 'tunnel') {
        $server += [
            'HTTP_X_FORWARDED_HOST' => 'cbed.example.devtunnels.ms',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];
    }
    $request = Request::create($origin.$path, $method, $data, $cookies, [], $server);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->isCleared()) {
            unset($cookies[$cookie->getName()]);
        } else {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
    }

    return $response;
}

function signIn(array &$cookies, User $user, string $password, bool $remember, string $host = 'local'): Response
{
    app('cache')->store('array')->flush();
    requestAs($cookies, '/login', host: $host);
    return requestAs($cookies, '/login', 'POST', [
        '_token' => app('session.store')->token(),
        'email' => $user->email,
        'password' => $password,
    ] + ($remember ? ['remember' => '1'] : []), $host);
}

function rememberCookie(Response $response): ?Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if (str_starts_with($cookie->getName(), 'remember_web_')) {
            return $cookie;
        }
    }

    return null;
}

check($user->fresh()->is_active && $user->fresh()->remember_token === null, 'Migration should activate existing accounts and expire legacy remember tokens.');
$browser = [];
check(requestAs($browser, '/login', 'POST')->getStatusCode() === 419, 'Login must require CSRF.');
$form = requestAs($browser, '/login')->getContent();
check(str_contains($form, 'Keep me signed in for 5 days') && ! preg_match('/name="remember"[^>]*checked/', $form), 'Remember opt-in should default to unchecked.');
$normal = signIn($browser, $user, $password, false);
check($normal->getStatusCode() === 302 && rememberCookie($normal) === null, 'Unchecked sign-in should omit the remember cookie.');
$sessionCookies = array_filter($normal->headers->getCookies(), fn (Cookie $cookie) => $cookie->getName() === config('session.cookie'));
check(count($sessionCookies) === 1 && reset($sessionCookies)->getExpiresTime() === 0, 'The ordinary session cookie should expire on browser close.');
check(requestAs($browser, '/signup')->getStatusCode() === 403, 'Remember support must not change account management permissions.');

foreach (['local', 'tunnel', 'https'] as $host) {
    $browser = [];
    $response = signIn($browser, $user, $password, true, $host);
    $remember = rememberCookie($response);
    check($remember !== null, "Expected a native remember cookie on {$host}. HTTP {$response->getStatusCode()}.");
    check(abs($remember->getExpiresTime() - time() - 5 * 24 * 60 * 60) < 10, 'Remember cookie should expire in five days.');
    check($remember->isHttpOnly() && $remember->getDomain() === null && $remember->getSameSite() === 'lax'
        && $remember->isSecure() === ($host !== 'local'), "Incorrect cookie scope on {$host}.");
    check($user->fresh()->getRememberToken() && ! str_contains($remember->getValue(), $password), 'Remember authentication should use a token.');

    $rememberedBrowser = [$remember->getName() => $remember->getValue()];
    $restored = requestAs($rememberedBrowser, '/_remember-me-test', host: $host);
    check($restored->getStatusCode() === 200 && json_decode($restored->getContent(), true)['remembered'], "Remember login should restore a lost session on {$host}.");
}

$stale = [$remember->getName() => $remember->getValue()];
$logout = requestAs($rememberedBrowser, '/logout', 'POST', ['_token' => app('session.store')->token()], 'https');
check($logout->getStatusCode() === 302 && rememberCookie($logout)?->isCleared(), 'Logout should delete the remember cookie.');
check(requestAs($stale, '/_remember-me-test', host: 'https')->getStatusCode() === 302, 'Logout should invalidate a copied remember cookie.');

$browser = [];
$remember = rememberCookie(signIn($browser, $user, $password, true));
$oldCookie = [$remember->getName() => $remember->getValue()];
$user->fresh()->update(['password' => 'ChangedPassword123!']);
check($user->fresh()->remember_token === null, 'Password change should revoke the remember token.');
check(requestAs($oldCookie, '/_remember-me-test')->getStatusCode() === 302, 'Password change should revoke cookie recovery.');
check(requestAs($browser, '/_remember-me-test')->getStatusCode() === 302, 'Password change should also revoke the existing session.');

$browser = [];
$remember = rememberCookie(signIn($browser, $user, 'ChangedPassword123!', true));
$oldCookie = [$remember->getName() => $remember->getValue()];
$admin = User::create(['name' => 'Status Admin', 'email' => 'status-admin@example.test', 'password' => $password, 'role' => User::ADMIN]);
$adminBrowser = [];
signIn($adminBrowser, $admin, $password, false);
$update = requestAs($adminBrowser, '/signup/'.$user->id, 'PUT', [
    '_token' => app('session.store')->token(),
    'first_name' => 'Remember',
    'last_name' => 'Tester',
    'email' => $user->email,
    'role' => $user->role,
    'is_active' => '0',
]);
check($update->getStatusCode() === 302 && ! $user->fresh()->is_active, 'Admins should be able to deactivate other accounts.');
check($user->fresh()->remember_token === null, 'Deactivation should revoke the remember token.');
check(requestAs($oldCookie, '/_remember-me-test')->getStatusCode() === 302, 'A deactivated account cannot recover a session.');
check(requestAs($browser, '/_remember-me-test')->getStatusCode() === 302, 'A deactivated account cannot continue an active session.');
$browser = [];
$inactive = signIn($browser, $user, 'ChangedPassword123!', true);
check(rememberCookie($inactive) === null && requestAs($browser, '/_remember-me-test')->getStatusCode() === 302, 'A deactivated account cannot sign in.');

$technician = User::create(['name' => 'Technician', 'email' => 'tech@example.test', 'password' => $password, 'role' => User::TECHNICIAN]);
$browser = [];
$response = signIn($browser, $technician, $password, true);
check(str_ends_with($response->headers->get('Location') ?? '', '/monitoring'), 'Technicians should still land on Monitoring.');

echo "Remembered login, HTTPS cookies, logout, password changes, account deactivation, and CSRF passed.\n";
