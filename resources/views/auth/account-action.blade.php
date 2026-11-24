@php
    $back = route('signup', request()->only('page', 'per_page'));
    $isOwnAccount = $selectedAccount->is(auth()->user());
    $roleLabel = $roles[$selectedAccount->role] ?? $selectedAccount->role;
    $fields = [
        'first_name' => ['First Name', $firstName, 'text', 127],
        'last_name' => ['Last Name', $lastName, 'text', 127],
        'email' => ['Email', $selectedAccount->email, 'email', 255],
    ];
@endphp

<dialog id="account-action" aria-labelledby="account-action-title" class="m-auto max-h-[90vh] w-11/12 max-w-lg overflow-y-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
    <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
        <h2 id="account-action-title" class="text-xl font-bold text-[#12345a]">{{ ucfirst($action) }} Account</h2>
        <a href="{{ $back }}" data-return-to-accounts aria-label="Close" class="rounded-md px-3 py-1 text-xl hover:bg-slate-100">&times;</a>
    </header>

    @if ($action === 'view')
        <dl class="space-y-4 break-words p-6">
            <div><dt class="text-sm text-slate-500">Full Name</dt><dd class="font-semibold">{{ $selectedAccount->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">Email</dt><dd class="font-semibold">{{ $selectedAccount->email }}</dd></div>
            <div><dt class="text-sm text-slate-500">Role</dt><dd class="font-semibold">{{ $roleLabel }}</dd></div>
        </dl>
    @elseif ($action === 'edit')
        <form method="POST" action="{{ route('signup.update', $selectedAccount, absolute: false) }}" class="space-y-4 p-6">
            @csrf
            @method('PUT')
            @if ($errors->accountEdit->any())
                <ul role="alert" class="list-inside list-disc rounded-lg bg-red-50 p-3 text-sm text-red-800">
                    @foreach ($errors->accountEdit->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            @foreach ($fields as $field => [$label, $value, $type, $maxLength])
                <label class="block text-sm font-semibold">{{ $label }}
                    <input name="{{ $field }}" type="{{ $type }}" value="{{ $errors->accountEdit->any() && is_string(old($field)) ? old($field) : $value }}"
                           required maxlength="{{ $maxLength }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal">
                </label>
            @endforeach
            @if ($isOwnAccount)
                <p class="text-sm"><strong>Role</strong> {{ $roleLabel }}</p>
                <input type="hidden" name="role" value="{{ $selectedAccount->role }}">
            @else
                <label class="block text-sm font-semibold">Role
                    <select name="role" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 font-normal">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(($errors->accountEdit->any() ? old('role') : $selectedAccount->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                <a href="{{ $back }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                <button type="submit" class="cbed-save-button">Save Changes</button>
            </div>
        </form>
    @else
        <form method="POST" action="{{ route('signup.destroy', $selectedAccount, absolute: false) }}" class="space-y-4 p-6">
            @csrf
            @method('DELETE')
            <p class="break-words">Delete <strong>{{ $selectedAccount->name }}</strong> ({{ $selectedAccount->email }})? This permanently removes the account.</p>
            <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                <a href="{{ $back }}" autofocus class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a>
                <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Delete Account</button>
            </div>
        </form>
    @endif
</dialog>
