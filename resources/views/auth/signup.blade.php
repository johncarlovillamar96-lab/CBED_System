@extends('layouts.admin')

@section('title', 'Accounts')

@section('content')
    @php
        $rowActions = [
            'view' => ['View details', 'fa-eye'],
            'edit' => ['Edit account', 'fa-pen-to-square'],
            'delete' => ['Delete account', 'fa-trash'],
        ];
    @endphp
    <x-admin-page-header title="Accounts">
        <button type="button" id="open-account-signup" aria-haspopup="dialog" aria-controls="account-signup" class="cbed-add-button">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create Account
        </button>
    </x-admin-page-header>

    <section aria-label="Account list" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="bg-[#103c60] text-xs uppercase text-white">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 min-w-40 bg-[#103c60] px-5 py-3">Full Name</th>
                        <th scope="col" class="px-5 py-3">Email</th>
                        <th scope="col" class="px-5 py-3">Role</th>
                        <th scope="col" class="px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounts as $account)
                        @php
                            $fullName = trim($account->first_name.' '.$account->last_name) ?: trim($account->name);
                        @endphp
                        <tr class="group hover:bg-slate-50">
                            <th scope="row" class="sticky left-0 z-10 break-words bg-white px-5 py-3 font-semibold text-slate-900 group-hover:bg-slate-50">{{ $fullName !== '' ? $fullName : 'Name not set' }}</th>
                            <td class="break-all px-5 py-3">{{ $account->email }}</td>
                            <td class="whitespace-nowrap px-5 py-3">{{ $roles[$account->role] ?? $account->role }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <div class="flex items-center">
                                    @foreach ($rowActions as $option => [$label, $icon])
                                        @if ($option !== 'delete' || ! $account->is(auth()->user()))
                                            <a href="{{ route('signup', ['action' => $option, 'account' => $account->id] + request()->only('page', 'per_page')) }}"
                                               aria-label="{{ $label }} for {{ $fullName !== '' ? $fullName : $account->email }}" title="{{ $label }}"
                                               class="cbed-record-action cbed-record-action--{{ $option }}">
                                                <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No accounts on this page.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.server-table-pagination', ['paginator' => $accounts, 'filterParams' => []])
    </section>

    @if ($selectedAccount)
        @include('auth.account-action')
    @endif

    <dialog id="account-signup" aria-labelledby="account-signup-title" @if ($errors->signup->any()) data-reopen @endif
            class="m-auto max-h-[90vh] w-11/12 max-w-lg overflow-y-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <h2 id="account-signup-title" class="text-xl font-bold text-[#12345a]">Create Account</h2>
            <button type="button" data-close-account-signup class="cbed-close-button" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </header>
        <form method="POST" action="{{ route('signup.store') }}" class="space-y-4 p-6">
            @csrf
            @if ($errors->signup->any())
                <ul class="list-inside list-disc rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">
                    @foreach ($errors->signup->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">First Name
                    <input name="first_name" value="{{ $errors->signup->any() && is_string(old('first_name')) ? old('first_name') : '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal" required maxlength="127" autocomplete="off" autofocus>
                </label>
                <label class="block text-sm font-semibold">Last Name
                    <input name="last_name" value="{{ $errors->signup->any() && is_string(old('last_name')) ? old('last_name') : '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal" required maxlength="127" autocomplete="off">
                </label>
            </div>
            <label class="block text-sm font-semibold">Email
                <input type="email" name="email" value="{{ $errors->signup->any() && is_string(old('email')) ? old('email') : '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal" required maxlength="255" autocomplete="off">
            </label>
            <label class="block text-sm font-semibold">Role
                <select name="role" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal" required>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(($errors->signup->any() ? old('role') : \App\Models\User::NON_ADMIN) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold">Password
                <x-password-input required :minlength="\App\Models\User::MIN_PASSWORD_LENGTH" maxlength="72" autocomplete="new-password" />
                <span class="mt-1 block text-xs font-normal text-slate-600">Use at least {{ \App\Models\User::MIN_PASSWORD_LENGTH }} characters.</span>
            </label>
            <label class="block text-sm font-semibold">Confirm password
                <x-password-input name="password_confirmation" required autocomplete="new-password" />
            </label>
            <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                <button type="button" data-close-account-signup class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button>
                <button type="submit" class="cbed-save-button">Create Account</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/password-visibility.js') }}" defer></script>
    <script>
        {
            const signupDialog = document.getElementById('account-signup');
            const signupButton = document.getElementById('open-account-signup');
            signupButton.addEventListener('click', () => signupDialog.showModal());
            signupDialog.querySelectorAll('[data-close-account-signup]').forEach(button => {
                button.addEventListener('click', () => signupDialog.close());
            });
            signupDialog.addEventListener('close', () => signupButton.focus());
            if (signupDialog.hasAttribute('data-reopen')) {
                signupDialog.showModal();
            }
            const accountAction = document.getElementById('account-action');
            if (accountAction) {
                accountAction.addEventListener('close', () => {
                    window.location.assign(accountAction.querySelector('[data-return-to-accounts]').href);
                });
                accountAction.showModal();
            }
        }
    </script>
@endpush
