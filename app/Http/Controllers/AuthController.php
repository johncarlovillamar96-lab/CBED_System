<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:72'],
        ]);

        if ($credentials['password'] !== 'ChangeMe123!' && strlen($credentials['password']) <= 72 && Auth::attemptWhen(
            $credentials,
            fn (User $user) => isset(User::ROLES[$user->role]),
            $request->boolean('remember'),
        )) {
            $request->session()->regenerate();

            if ($request->user()->role === User::TECHNICIAN) {
                $request->session()->forget('url.intended');

                return redirect()->route('ui.monitoring');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showSignup(Request $request)
    {
        $perPage = TablePagination::size($request);
        $accounts = User::query()->latest('id')->paginate($perPage, ['id', 'name', 'first_name', 'last_name', 'email', 'role'])
            ->appends(['per_page' => $perPage]);

        $action = $request->query('action');
        $selectedAccount = null;
        $firstName = $lastName = null;
        if (in_array($action, ['view', 'edit', 'delete'], true)) {
            $id = $request->query('account');
            abort_unless(is_string($id) && ctype_digit($id), 404);
            $selectedAccount = User::findOrFail($id);
            abort_if($action === 'delete' && $selectedAccount->is($request->user()), 403);
            $firstName = $selectedAccount->first_name;
            $lastName = $selectedAccount->last_name;
            if ($action === 'edit' && $firstName === null && $lastName === null) {
                $name = trim($selectedAccount->name);
                preg_match('/^(.*)\s+(\S+)$/u', $name, $parts);
                $firstName = $parts[1] ?? $name;
                $lastName = $parts[2] ?? '';
            }
        }

        return view('auth.signup', compact('accounts', 'action', 'selectedAccount', 'firstName', 'lastName') + ['roles' => User::ROLES]);
    }

    public function signup(Request $request)
    {
        $data = $request->validateWithBag('signup', [
            'first_name' => ['required', 'string', 'max:127'],
            'last_name' => ['required', 'string', 'max:127'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'bail', 'required', 'string', 'confirmed', Password::min(User::MIN_PASSWORD_LENGTH), Rule::notIn(['ChangeMe123!']),
                function (string $attribute, string $value, \Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('The password must be at most 72 bytes.');
                    }
                },
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        $user = User::create([
            'name' => $data['first_name'].' '.$data['last_name'],
        ] + $data);

        return redirect()->route('signup')->with([
            'success' => 'Account created',
            'success_description' => "The new user can sign in with {$user->email}.",
        ]);
    }

    public function updateAccount(Request $request, int $account)
    {
        $data = $request->validateWithBag('accountEdit', [
            'first_name' => ['required', 'string', 'max:127'],
            'last_name' => ['required', 'string', 'max:127'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ]);

        $adminId = (int) $request->user()->getKey();
        abort_if($adminId === $account && $data['role'] !== User::ADMIN, 403, 'You cannot change your own role.');

        DB::transaction(function () use ($adminId, $account, $data) {
            $users = User::whereKey([$adminId, $account])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($users->get($adminId)?->isAdmin(), 403);
            $user = $users->get($account);
            abort_unless($user, 404);
            $user->update(['name' => $data['first_name'].' '.$data['last_name']] + $data);
        }, 3);

        return redirect()->route('signup')->with('success', 'Account updated.');
    }

    public function deleteAccount(Request $request, int $account)
    {
        $adminId = (int) $request->user()->getKey();
        abort_if($adminId === $account, 403, 'You cannot delete your own account.');

        DB::transaction(function () use ($adminId, $account) {
            $users = User::whereKey([$adminId, $account])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($users->get($adminId)?->isAdmin(), 403);
            $user = $users->get($account);
            abort_unless($user, 404);
            $user->delete();
        }, 3);

        return redirect()->route('signup')->with('success', 'Account deleted.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
